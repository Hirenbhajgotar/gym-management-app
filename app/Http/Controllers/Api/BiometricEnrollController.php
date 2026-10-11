<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\Exceptions\BiometricConsentRequiredException;
use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\Services\BiometricTemplatePropagator;
use App\Biometrics\Services\BiometricTemplateSuperseder;
use App\Biometrics\ValueObjects\BiometricPropagationResult;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiometricEnrollRequest;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class BiometricEnrollController extends Controller
{
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

        try {
            $templateRef = $provider->enroll($gymMember, $sample);

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

            $template = DB::transaction(function () use ($device, $gymMember, $pins, $templateRef, $biometricType): BiometricTemplate {
                // Re-check fresh consent inside transaction to prevent TOCTOU race
                throw_unless(
                    $gymMember->hasActiveBiometricConsent(fresh: true),
                    BiometricConsentRequiredException::class
                );

                // Cancel any pending or in-flight delete commands on this device for this member
                AdmsDeviceCommand::cancelDeleteCommands($device->id, $pins);

                // Supersede older templates for this member, device and modality
                $this->superseder->supersedeForNewEnrolment(
                    $device->gym_id,
                    $gymMember,
                    $device->id,
                    $biometricType->value
                );

                // Create BiometricTemplate record
                return BiometricTemplate::query()->create([
                    'gym_member_id' => $gymMember->id,
                    'gym_id' => $device->gym_id,
                    'enrolled_by_device_id' => $device->id,
                    'biometric_type' => $biometricType->value,
                    'provider_type' => $device->provider_type,
                    'template_ref' => $templateRef->toJson(),
                    'quality_score' => $templateRef->meta['quality_score'] ?? null,
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
        } catch (BiometricConsentRequiredException $e) {
            Log::warning('BiometricEnrollController::enroll aborted: consent revoked during enrollment', [
                'device_id' => $device->id,
                'member_id' => $gymMember->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Biometric consent required',
                'message' => 'Member must have an active biometric consent on file',
            ], 422);
        } catch (Exception $e) {
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
                // Re-check fresh consent inside transaction to prevent TOCTOU race
                throw_unless(
                    $gymMember->hasActiveBiometricConsent(fresh: true),
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
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($template) {
                    $template->update([
                        'biometric_type' => $biometricType,
                        'provider_type' => $device->provider_type ? $device->provider_type->value : 'device_native',
                        'template_ref' => $templateRef,
                        'quality_score' => $validated['quality_score'] ?? null,
                        'is_active' => true,
                        'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
                        'enrolled_at' => now(),
                        'hardware_confirmed_at' => now(),
                    ]);
                } else {
                    $existingActive = BiometricTemplate::query()
                        ->where('gym_member_id', $gymMemberId)
                        ->where('gym_id', $device->gym_id)
                        ->where('enrolled_by_device_id', $device->id)
                        ->where('biometric_type', $biometricType)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if ($existingActive) {
                        $existingActive->update([
                            'biometric_type' => $biometricType,
                            'provider_type' => $device->provider_type ? $device->provider_type->value : 'device_native',
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
                            'provider_type' => $device->provider_type ? $device->provider_type->value : 'device_native',
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
                    $gymMember,
                    $device->id,
                    $biometricType instanceof BiometricTypeEnum ? $biometricType->value : $biometricType,
                    $template->id
                );

                // Fan out inside the caller's transaction so a consent revocation racing this
                // request cannot slip between the re-check above and the sibling writes (OpenSpec §4).
                $propagation = $this->propagator->propagateToSiblingDevices($device, $gymMember, $template);

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

        // Verify template belongs to this device's gym
        if ($template->gym_id !== $device->gym_id) {
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
}
