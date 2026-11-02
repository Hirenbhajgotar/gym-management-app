<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\Exceptions\BiometricConsentRequiredException;
use App\Biometrics\Exceptions\MemberArchivedException;
use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\Services\BiometricTemplatePropagator;
use App\Biometrics\Services\BiometricTemplateSuperseder;
use App\Biometrics\ValueObjects\BiometricPropagationResult;
use App\Enums\BiometricProvenanceEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiometricEnrollRequest;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use App\Traits\AssertsTransactionalAuditSink;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class BiometricEnrollController extends Controller
{
    use AssertsTransactionalAuditSink;

    public function __construct(
        protected BiometricProviderRegistry $registry,
        protected BiometricDataDeletionService $deletionService,
        protected BiometricTemplatePropagator $propagator,
        protected BiometricTemplateSuperseder $superseder
    ) {}

    public function enroll(BiometricEnrollRequest $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        // Scope member lookup to device's gym (prevents cross-gym enrollment)
        /** @var GymMember $gymMember */
        $gymMember = GymMember::query()->where('gym_id', $device->gym_id)
            ->findOrFail($request->validated('gym_member_id'));

        // Archived members are access-revoked: they must never gain (or regain) a live
        // credential, so enrollment is refused outright (see MemberArchiveService).
        if (! $gymMember->mayHoldBiometricCredential()) {
            return response()->json($this->archivedMemberRefusalPayload(), 422);
        }

        // Enforce that active consent exists - do not auto-create
        if (! $gymMember->hasActiveBiometricConsent(fresh: true)) {
            return response()->json([
                'success' => false,
                'error' => 'Biometric consent required',
                'message' => 'Member must have an active biometric consent on file',
            ], 422);
        }

        if ($device->isDeviceNative() || ! $device->hasCloudProvider() || ! $device->provider_type) {
            return response()->json([
                'success' => false,
                'error' => 'Device-native enrollment required',
                'message' => 'This device operates in device-native mode. Use /enroll-sync to register on-device templates.',
            ], 422);
        }

        // Get provider and enroll
        $provider = $this->registry->resolve($device->provider_type, is_array($device->provider_config) ? $device->provider_config : []);

        /** @var BiometricTypeEnum $biometricType */
        $biometricType = $request->validated('biometric_type');
        $rawData = $request->validated('data');
        $rawMeta = $request->validated('metadata');
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($rawMeta) ? $rawMeta : [];

        $sample = new BiometricSample(
            type: $biometricType->value,
            data: is_string($rawData) ? $rawData : '',
            metadata: $metadata
        );

        // The remote template a cloud provider creates is NOT transactional: a
        // rollback of the local row does not un-provision it. Captured here so
        // every failure path can compensate the provider-side side effect.
        $createdRef = null;

        // "A remote template may exist" is a distinct fact from "we hold its
        // reference". $createdRef is only assigned when enroll() RETURNS, so a
        // provider that creates the template and then throws (AzureFaceProvider's
        // trainPersonGroup flow) leaves the side effect with no handle. This flag
        // is what keeps the refusal from being silent about that case.
        $enrolmentAttempted = false;

        try {
            $template = DB::transaction(function () use ($device, $gymMember, $biometricType, $sample, $provider, &$createdRef, &$enrolmentAttempted): BiometricTemplate {
                // Acquire the archive truth BEFORE the side effect and hold it.
                // lockForUpdate() takes the same row lock that
                // MemberArchiveService::archive() takes, so a concurrent archive
                // either commits before this read (and is seen here) or waits
                // for this transaction to commit. The previous refresh() was a
                // plain, non-locking SELECT whose read view — under MySQL's
                // default REPEATABLE READ — is fixed at first read, so an
                // archive committing after it was invisible.
                $locked = $this->lockAndAssertNotArchived($gymMember);

                // Re-check fresh consent inside the transaction (same TOCTOU).
                throw_unless(
                    $locked->hasActiveBiometricConsent(fresh: true),
                    BiometricConsentRequiredException::class
                );

                // Provider-side enrolment, performed while the row lock is held
                // so the member cannot be archived between the check above and
                // the credential being created. The attempt flag is set BEFORE
                // the call: if the provider creates the template and then throws,
                // the catch arms must still know a remote credential may exist.
                $enrolmentAttempted = true;
                $createdRef = $provider->enroll($locked, $sample);

                // Belt-and-braces: nothing may have flipped the row archived
                // while the credential was being created. A cross-connection
                // archive is blocked by the lock, but a writer running inside
                // this same transaction would be visible here; if so, roll the
                // local row back and let the catch compensate the remote one.
                throw_unless($locked->refresh()->mayHoldBiometricCredential(), MemberArchivedException::class);

                // Resolve command PINs (both member ID and any existing template refs)
                $existingTemplates = BiometricTemplate::query()
                    ->where('gym_id', $device->gym_id)
                    ->where('gym_member_id', $gymMember->id)
                    ->where('enrolled_by_device_id', $device->id)
                    ->get();

                $pins = [(string) $gymMember->id];
                foreach ($existingTemplates as $existingTemplate) {
                    $pins[] = $existingTemplate->resolvedCommandPin();
                }
                $pins = array_values(array_unique($pins));

                // Cancel any pending or in-flight delete commands on this device for this member
                AdmsDeviceCommand::cancelDeleteCommands($device->id, $pins);

                // Supersede older templates for this member, device and modality.
                //
                // This path is gated above to cloud-capable devices and provisions a
                // provider credential, so it may only demote cloud rows. The terminal
                // credential on a dual-mode device is a different credential and stays live
                // for its own hardware erasure.
                $this->superseder->supersedeForNewEnrolment(
                    $device->gym_id,
                    $gymMember,
                    $device->id,
                    $biometricType->value,
                    BiometricProvenanceEnum::Cloud
                );

                // Create BiometricTemplate record. The provider is stored as its backing value:
                // `provider_type` is string-cast on the model, and assigning the enum instance
                // here would defeat the cast and the strict `hasCloudProvider()` comparison.
                return BiometricTemplate::query()->create([
                    'gym_member_id' => $gymMember->id,
                    'gym_id' => $device->gym_id,
                    'enrolled_by_device_id' => $device->id,
                    'biometric_type' => $biometricType->value,
                    'provider_type' => $device->provider_type->value,
                    'template_ref' => $createdRef->toJson(),
                    'quality_score' => $createdRef->meta['quality_score'] ?? null,
                    'is_active' => true,
                    'enrolled_at' => now(),
                ]);
            });

            return response()->json([
                'success' => true,
                'template_id' => $template->id,
                'quality_score' => $template->quality_score,
                'enrolled_at' => $template->enrolled_at !== null ? $template->enrolled_at->toISOString() : now()->toISOString(),
            ]);
        } catch (MemberArchivedException $e) {
            $this->compensateRemoteEnrolment($provider, $createdRef, $device, $gymMember, $biometricType, $enrolmentAttempted);

            Log::warning('BiometricEnrollController::enroll aborted: member archived during enrollment', [
                'device_id' => $device->id,
                'member_id' => $gymMember->id,
            ]);

            return response()->json($this->archivedMemberRefusalPayload(), 422);
        } catch (BiometricConsentRequiredException $e) {
            $this->compensateRemoteEnrolment($provider, $createdRef, $device, $gymMember, $biometricType, $enrolmentAttempted);

            Log::warning('BiometricEnrollController::enroll aborted: consent revoked during enrollment', [
                'device_id' => $device->id,
                'member_id' => $gymMember->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Biometric consent required',
                'message' => 'Member must have an active biometric consent on file',
            ], 422);
        } catch (Throwable $e) {
            $this->compensateRemoteEnrolment($provider, $createdRef, $device, $gymMember, $biometricType, $enrolmentAttempted);

            Log::error('BiometricEnrollController::enroll failed', [
                'device_id' => $device->id,
                'member_id' => $gymMember->id,
                'error_type' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Enrollment failed',
                'message' => 'An error occurred during enrollment.',
            ], 422);
        }
    }

    public function syncFromDevice(Request $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        /** @var array{gym_member_id: int|string, biometric_type: string|BiometricTypeEnum, template_ref: string, quality_score?: float|int|null} $validated */
        $validated = $request->validate([
            'gym_member_id' => [
                'required',
                'exists:gym_members,id',
                // Ensure member is same gym as device
                Rule::exists('gym_members', 'id')
                    ->where('gym_id', $device->gym_id),
            ],
            'biometric_type' => ['required', Rule::enum(BiometricTypeEnum::class)],
            'template_ref' => ['required', 'max:255'], // device internal ID / PIN (accept string or integer)
            'quality_score' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        $gymMemberId = (int) $validated['gym_member_id'];
        $templateRef = $validated['template_ref'];

        if ($templateRef !== (string) $gymMemberId) {
            return response()->json([
                'success' => false,
                'error' => 'Template reference mismatch',
                'message' => 'Device template PIN must match member ID',
            ], 422);
        }

        /** @var GymMember|null $gymMember */
        $gymMember = GymMember::query()
            ->where('gym_id', $device->gym_id)
            ->find($gymMemberId);

        // Archived members are access-revoked: they must never gain (or regain) a live
        // credential, so enrollment is refused outright (see MemberArchiveService).
        if ($gymMember && ! $gymMember->mayHoldBiometricCredential()) {
            return response()->json($this->archivedMemberRefusalPayload(), 422);
        }

        // Consent check — deny if absent
        if (! $gymMember || ! $gymMember->hasActiveBiometricConsent(fresh: true)) {
            return response()->json([
                'success' => false,
                'error' => 'Member has not provided biometric consent',
            ], 422);
        }

        try {
            /** @var array{confirmedTemplate: BiometricTemplate, propagation: BiometricPropagationResult} $enrollment */
            $enrollment = DB::transaction(function () use ($gymMemberId, $device, $validated, $templateRef, $gymMember): array {
                // Lock the member row and re-assert archive eligibility inside the
                // transaction. This is the same row lock MemberArchiveService::archive()
                // takes, so a concurrent archive either commits before this read (and is
                // seen here) or waits for this transaction to commit — it cannot slip
                // between this check and the credential writes below. The previous
                // refresh() was a plain, non-locking SELECT.
                $locked = $this->lockAndAssertNotArchived($gymMember);

                // Re-check fresh consent inside transaction to prevent TOCTOU race
                throw_unless(
                    $locked->hasActiveBiometricConsent(fresh: true),
                    BiometricConsentRequiredException::class
                );

                $existingTemplates = BiometricTemplate::query()
                    ->where('gym_id', $device->gym_id)
                    ->where('gym_member_id', $gymMemberId)
                    ->where('enrolled_by_device_id', $device->id)
                    ->get();

                $pins = [(string) $gymMemberId];
                foreach ($existingTemplates as $existingTemplate) {
                    $pins[] = $existingTemplate->resolvedCommandPin();
                }
                $pins = array_values(array_unique($pins));
                AdmsDeviceCommand::cancelDeleteCommands($device->id, $pins);

                $biometricType = $validated['biometric_type'];

                $template = BiometricTemplate::query()
                    ->where('gym_member_id', $gymMemberId)
                    ->where('gym_id', $device->gym_id)
                    ->where('enrolled_by_device_id', $device->id)
                    ->where('biometric_type', $biometricType)
                    ->where('sync_status', BiometricTemplateSyncStatusEnum::Pending)
                    ->hardwareBacked()
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($template) {
                    $template->update([
                        'biometric_type' => $biometricType,
                        'provider_type' => BiometricProvidersEnum::DeviceNative->value,
                        'template_ref' => $templateRef,
                        'quality_score' => $validated['quality_score'] ?? null,
                        'is_active' => true,
                        'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
                        'enrolled_at' => now(),
                        'hardware_confirmed_at' => now(),
                    ]);
                } else {
                    // Only a row this device authored may be updated in place. The selection
                    // deliberately asks for a hardware-backed row: a device-reported sync
                    // must never change the provenance of a row it did not create. Matching
                    // on member + modality alone let an ADMS `enroll-sync` claim the
                    // `is_active` cloud row `enroll()` had created on a dual-mode device,
                    // overwriting its `provider_type` and its `template_ref` and orphaning
                    // the provider credential while the app reported the member revoked.
                    $existingActive = BiometricTemplate::query()
                        ->where('gym_member_id', $gymMemberId)
                        ->where('gym_id', $device->gym_id)
                        ->where('enrolled_by_device_id', $device->id)
                        ->where('biometric_type', $biometricType)
                        ->where('is_active', true)
                        ->hardwareBacked()
                        ->lockForUpdate()
                        ->first();

                    if ($existingActive) {
                        $existingActive->update([
                            'biometric_type' => $biometricType,
                            'provider_type' => BiometricProvidersEnum::DeviceNative->value,
                            'template_ref' => $templateRef,
                            'quality_score' => $validated['quality_score'] ?? null,
                            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
                            'enrolled_at' => now(),
                            'hardware_confirmed_at' => now(),
                        ]);
                        $template = $existingActive;
                    } else {
                        $template = BiometricTemplate::query()->create([
                            'gym_member_id' => $gymMemberId,
                            'gym_id' => $device->gym_id,
                            'enrolled_by_device_id' => $device->id,
                            'biometric_type' => $biometricType,
                            'provider_type' => BiometricProvidersEnum::DeviceNative->value,
                            'template_ref' => $templateRef,
                            'quality_score' => $validated['quality_score'] ?? null,
                            'is_active' => true,
                            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
                            'enrolled_at' => now(),
                            'hardware_confirmed_at' => now(),
                        ]);
                    }
                }

                $this->superseder->supersedeForNewEnrolment(
                    $device->gym_id,
                    $locked,
                    $device->id,
                    $biometricType instanceof BiometricTypeEnum ? $biometricType->value : $biometricType,
                    BiometricProvenanceEnum::Hardware,
                    $template->id
                );

                // Fan out inside the caller's transaction so a consent revocation racing this
                // request cannot slip between the re-check above and the sibling writes (OpenSpec §4).
                $propagation = $this->propagator->propagateToSiblingDevices($device, $locked, $template);

                return [
                    'confirmedTemplate' => $template,
                    'propagation' => $propagation,
                ];
            });

            $confirmedTemplate = $enrollment['confirmedTemplate'];
            $propResult = $enrollment['propagation'];

            activity('biometric')
                ->performedOn($gymMember)
                ->withProperties([
                    'device_id' => $device->id,
                    'member_id' => $gymMemberId,
                    'template_id' => $confirmedTemplate->id,
                    'queued_count' => $propResult->queuedCount,
                ])
                ->log('Biometric enrollment confirmed by device');
        } catch (Throwable $e) {
            if ($e instanceof MemberArchivedException) {
                Log::warning('syncFromDevice: enrollment rejected because member is archived', [
                    'device_id' => $device->id,
                    'member_id' => $gymMemberId,
                ]);

                return response()->json($this->archivedMemberRefusalPayload(), 422);
            }

            $isConsentRefusal = $e instanceof BiometricConsentRequiredException;

            if ($isConsentRefusal) {
                Log::warning('syncFromDevice: enrollment rejected due to missing active biometric consent', [
                    'device_id' => $device->id,
                    'member_id' => $gymMemberId,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Consent Required',
                    'message' => 'Member has not provided active biometric consent.',
                ], 422);
            }

            Log::error('syncFromDevice: enrollment transaction failed', [
                'device_id' => $device->id,
                'member_id' => $gymMemberId,
                'error_type' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to save biometric enrollment',
            ], 500);
        }

        $hasTotalFanoutFailure = $propResult->totalFanoutFailed();

        $responsePayload = [
            'success' => ! $hasTotalFanoutFailure,
            'queued_count' => $propResult->queuedCount,
            'skipped_count' => $propResult->alreadyProvisionedCount,
        ];

        if ($propResult->hasFailures()) {
            if (! $hasTotalFanoutFailure) {
                $responsePayload['partial_success'] = true;
            }
            $responsePayload['failed_devices'] = array_map(
                fn (int $id, string $name): array => ['id' => $id, 'name' => $name],
                $propResult->failedDeviceIds,
                $propResult->failedDeviceNames
            );
        }

        return response()->json($responsePayload);
    }

    public function deleteTemplate(Request $request, BiometricTemplate $template): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        // Verify the template belongs to this device's gym AND was enrolled by this
        // device. A device token must not drive a sibling device's erasure — that
        // credential lives on hardware this caller does not own — nor stamp the
        // Art. 17 request marker (`deleteSingleTemplate()`) on another device's row.
        if ($template->gym_id !== $device->gym_id || $template->enrolled_by_device_id !== $device->id) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Template does not belong to this device',
            ], 403);
        }

        try {
            $success = $this->deletionService->deleteSingleTemplate($template);

            if (! $success) {
                return response()->json([
                    'success' => false,
                    'error' => 'Deletion failed',
                    'message' => 'Provider-side template deletion was not confirmed.',
                ], 422);
            }

            // `deleteSingleTemplate()` returning true means the obligation is durably recorded,
            // not that the terminal has confirmed removal. Report the lifecycle state the row
            // actually reached so the caller is never told a credential was erased while it is
            // still sitting on a terminal.
            $template->refresh();
            $state = $template->sync_status;
            $confirmed = $state === BiometricTemplateSyncStatusEnum::Deleted;

            return response()->json([
                'success' => true,
                'confirmed' => $confirmed,
                'sync_status' => $state?->value,
                'message' => $confirmed
                    ? 'Template deleted successfully'
                    : 'Hardware erasure requested; the record is retained until the device confirms removal.',
            ]);
        } catch (Exception $e) {
            Log::error('deleteTemplate failed', [
                'template_id' => $template->id,
                'device_id' => $device->id,
                'error_type' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Deletion failed',
                'message' => 'An error occurred while deleting the biometric template.',
            ], 422);
        }
    }

    /**
     * The refusal both enrollment entry points return for an archived member.
     *
     * `member_archived` is the same reason string MembershipGuard names and the
     * device-facing webhook maps, so one archived member reads the same way at
     * every decision point that can touch their credential — and an operator
     * reading a device log can tell an access revocation apart from a consent
     * problem without needing to know which endpoint answered.
     *
     * @return array<string, mixed>
     */
    private function archivedMemberRefusalPayload(): array
    {
        return [
            'success' => false,
            'error' => 'member_archived',
            'message' => 'Member is archived. Enrollment is not permitted for archived members.',
        ];
    }

    /**
     * Lock the member row and re-assert archive eligibility inside the enrollment transaction.
     *
     * The initial gate reads the member outside the transaction, so an archiving
     * write landing in between would otherwise enroll against the stale copy — the
     * same TOCTOU the consent re-check beside it exists to close. `lockForUpdate()`
     * takes the same row lock MemberArchiveService::archive() takes, so the archive
     * either commits before this read (and is seen here) or waits for this
     * transaction to commit; unlike a plain refresh(), it cannot be satisfied by a
     * snapshot fixed before the concurrent write landed. The freshly-read row is
     * returned so callers act on the locked truth rather than the stale copy they
     * arrived with, and the exception is caught by the caller, which turns it into
     * the same 422 the initial gate returns.
     *
     * @throws MemberArchivedException
     */
    private function lockAndAssertNotArchived(GymMember $member): GymMember
    {
        $locked = GymMember::query()
            ->whereKey($member->getKey())
            ->lockForUpdate()
            ->first();

        throw_unless($locked instanceof GymMember, MemberArchivedException::class);

        $locked->assertMayHoldBiometricCredential();

        return $locked;
    }

    /**
     * Undo a provider-side enrolment that no local row will record.
     *
     * A cloud provider's enroll() is not transactional: rolling back the local
     * template row leaves the remote credential live, so every failure path calls
     * this to erase the template that was created. The compensation is best-effort
     * — a provider outage must not mask the original failure or turn the device's
     * error response into a 500 — but it is never silent: when the erase cannot be
     * confirmed (the provider returned false, threw, or never handed back a
     * reference), a durable orphan marker is written so the credential can be
     * reconciled instead of surviving invisibly behind a refusal.
     *
     * `$enrolmentAttempted` matters because `$ref` is only set when enroll()
     * returns. A provider that creates the template and then throws — the flow
     * AzureFaceProvider's trainPersonGroup step can reach — yields a live
     * credential with no reference, and the flag is the only thing that
     * distinguishes that from "nothing was ever created".
     */
    private function compensateRemoteEnrolment(
        BiometricProvider $provider,
        ?TemplateRef $ref,
        BiometricDevice $device,
        GymMember $member,
        BiometricTypeEnum $biometricType,
        bool $enrolmentAttempted
    ): void {
        if (! $enrolmentAttempted) {
            return;
        }

        // A create-then-throw leaves a possible remote template with no handle.
        // There is nothing to erase with, so the attempt itself is recorded.
        if (! $ref instanceof TemplateRef) {
            $this->recordOrphanedRemoteTemplate(
                $device,
                $member,
                $biometricType,
                null,
                'provider threw after creating, before returning a template reference'
            );

            return;
        }

        try {
            if ($provider->deleteTemplate($ref) === true) {
                return;
            }
        } catch (Throwable $e) {
            Log::error('BiometricEnrollController::compensateRemoteEnrolment failed to erase orphaned remote template', [
                'device_id' => $device->id,
                'member_id' => $member->id,
                'provider' => $ref->provider,
                'external_id' => $ref->externalId,
                'error_type' => $e::class,
            ]);

            $this->recordOrphanedRemoteTemplate($device, $member, $biometricType, $ref, 'provider erase threw ' . $e::class);

            return;
        }

        // Every shipped provider reports a failed erase by RETURNING false
        // (AzureFaceProvider, BioIDProvider, CustomApiProvider). Ignoring that
        // return value is what silently treated a failed erase as success.
        $this->recordOrphanedRemoteTemplate(
            $device,
            $member,
            $biometricType,
            $ref,
            'provider did not confirm the erase (deleteTemplate returned false)'
        );
    }

    /**
     * Record a remote template a refused enrolment may have left behind.
     *
     * The row is a durable erasure obligation, flagged `is_erasure_obligation` so no later
     * enrolment can supersede it (that would cancel the obligation and write a false
     * "erasure overridden" audit line for an erasure the member never withdrew).
     *
     * With a reference it is `PendingDeletion`, so
     * `biometrics:reconcile-pending-deletions` retries the erase against the provider. Without
     * one — a create-then-throw — nothing can ever erase it, so it is `DeletionUnresolvable`:
     * a terminal state the reconciler does not re-select, surfaced to operators, rather than an
     * hourly retry loop that permanently blocks the member's own GDPR erasure.
     */
    private function recordOrphanedRemoteTemplate(
        BiometricDevice $device,
        GymMember $member,
        BiometricTypeEnum $biometricType,
        ?TemplateRef $ref,
        string $reason
    ): void {
        $status = $ref instanceof TemplateRef
            ? BiometricTemplateSyncStatusEnum::PendingDeletion
            : BiometricTemplateSyncStatusEnum::DeletionUnresolvable;

        try {
            DB::transaction(function () use ($device, $member, $biometricType, $ref, $reason, $status): void {
                // `is_erasure_obligation` is a compliance discriminator and is deliberately not
                // mass-assignable, so the marker row is forced rather than filled.
                $template = BiometricTemplate::query()->forceCreate([
                    'gym_member_id' => $member->id,
                    'gym_id' => $device->gym_id,
                    'enrolled_by_device_id' => $device->id,
                    'biometric_type' => $biometricType->value,
                    // `??` is itself null-safe: a null `$ref` or a never-configured device falls
                    // through to the next operand instead of raising, so the nullsafe operators are
                    // redundant here.
                    'provider_type' => $ref->provider ?? $device->provider_type->value ?? 'unknown',
                    'template_ref' => $ref?->toJson(),
                    'is_active' => false,
                    'is_erasure_obligation' => true,
                    'sync_status' => $status,
                    'enrolled_at' => null,
                ]);

                if ($status === BiometricTemplateSyncStatusEnum::DeletionUnresolvable) {
                    // The one park writer: state, durable flag and transactional audit entry.
                    // A null reference has no automated exit, so the row must be self-describing
                    // and visible to the operator surface from the moment it is written.
                    $template->parkAsUnresolvableObligation(
                        'a refused enrolment may have left a remote template with no reference',
                        [
                            'device_id' => $device->id,
                            'member_id' => $member->id,
                            'provider' => $ref?->provider,
                            'reason' => $reason,
                        ]
                    );

                    return;
                }

                // A reference-backed orphan is re-driven by the reconciler, but the entry that
                // says why the row exists must be as durable as the row itself.
                $this->assertAuditSinkIsTransactional();

                $logged = activity('biometric')
                    ->performedOn($template)
                    ->withProperties([
                        'template_id' => $template->id,
                        'gym_id' => $device->gym_id,
                        'member_id' => $member->id,
                        'device_id' => $device->id,
                        'provider' => $ref?->provider,
                        'sync_status' => $status->value,
                        'reason' => $reason,
                    ])
                    ->log('Biometric template orphaned by refused enrolment and queued for provider erasure');

                if ($logged === null) {
                    throw new RuntimeException(
                        'The orphaned remote template was not recorded because its audit entry could not be written.'
                    );
                }
            });
        } catch (Throwable $e) {
            // Losing the marker is worse than losing the log line, so both are
            // attempted and the failure to persist is recorded loudly.
            Log::error('BiometricEnrollController::recordOrphanedRemoteTemplate could not persist the orphan marker', [
                'device_id' => $device->id,
                'member_id' => $member->id,
                'provider' => $ref?->provider,
                'error_type' => $e::class,
            ]);
        }

        Log::warning('BiometricEnrollController: refused enrolment left an unconfirmed remote template; recorded for reconciliation', [
            'device_id' => $device->id,
            'member_id' => $member->id,
            'provider' => $ref?->provider,
            'external_id' => $ref?->externalId,
            'reason' => $reason,
            'sync_status' => $status->value,
        ]);
    }
}
