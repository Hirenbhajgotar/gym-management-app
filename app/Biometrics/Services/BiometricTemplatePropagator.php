<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\Exceptions\BiometricConsentRequiredException;
use App\Biometrics\Exceptions\MemberArchivedException;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Biometrics\ValueObjects\BiometricPropagationResult;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BiometricTemplatePropagator
{
    public function __construct(
        protected BiometricTemplateSuperseder $superseder
    ) {}

    /**
     * Propagate enrolled biometric profile to all other active ADMS devices in the gym.
     *
     * @throws MemberArchivedException when the member is archived at fan-out time
     * @throws BiometricConsentRequiredException when consent is absent at fan-out time
     */
    public function propagateToSiblingDevices(
        BiometricDevice $originatingDevice,
        GymMember $member,
        BiometricTemplate $template
    ): BiometricPropagationResult {
        // Fan-out creates/user_adds credentials on every sibling, so it is a
        // grant-side producer and must ask the same archive question as the
        // originating enrolment. Today's sole caller does so inside the enrol
        // transaction's row lock, but that coupling disappears if the fan-out
        // ever moves to a queued job — so the check is stated here, not assumed.
        $member->assertMayHoldBiometricCredential();

        $siblingDevices = BiometricDevice::query()
            ->where('gym_id', $originatingDevice->gym_id)
            ->where('id', '!=', $originatingDevice->id)
            ->active()
            ->admsEnabled()
            ->get();

        if ($siblingDevices->isEmpty()) {
            return new BiometricPropagationResult(
                queuedCount: 0,
                alreadyProvisionedCount: 0,
                failedDeviceIds: [],
                failedDeviceNames: [],
                totalSiblings: 0
            );
        }

        $commandPin = BiometricTemplate::resolveCommandPin($template->template_ref, $member->id);
        $queuedCount = 0;
        $alreadyProvisionedCount = 0;
        $failedDeviceIds = [];
        $failedDeviceNames = [];

        foreach ($siblingDevices as $sibling) {
            try {
                DB::transaction(function () use ($sibling, $member, $template, $commandPin): void {
                    throw_unless(
                        $member->hasActiveBiometricConsent(fresh: true),
                        BiometricConsentRequiredException::class
                    );

                    $cancelledDeletes = AdmsDeviceCommand::cancelInFlightFor(
                        $sibling->id,
                        $commandPin,
                        commandType: 'user_delete'
                    );
                    if ($cancelledDeletes > 0) {
                        Log::info('BiometricTemplatePropagator: cancelled in-flight delete commands on sibling', [
                            'sibling_device_id' => $sibling->id,
                            'member_id' => $member->id,
                            'count' => $cancelledDeletes,
                        ]);
                    }

                    $this->supersedePendingDeletionSiblings($sibling, $member, $template);

                    $this->upsertSiblingTemplate($sibling, $member, $template);

                    $idempotencyKey = AdmsCommandKey::buildUserAdd(
                        $sibling->gym_id,
                        $sibling->id,
                        $member->id,
                        $template->id,
                        AdmsCommandKey::STAGE_SYNC
                    );

                    AdmsDeviceCommand::cancelInFlightFor($sibling->id, $commandPin, $idempotencyKey);

                    AdmsDeviceCommand::query()->create([
                        'gym_id' => $sibling->gym_id,
                        'device_id' => $sibling->id,
                        'command_type' => 'user_add',
                        'command_payload' => [
                            'Pin' => $commandPin,
                            'Name' => $member->name,
                        ],
                        'status' => AdmsCommandStatusEnum::PENDING,
                        'idempotency_key' => $idempotencyKey,
                    ]);
                });

                $queuedCount++;
            } catch (UniqueConstraintViolationException $e) {
                $alreadyProvisionedCount++;
                Log::warning('BiometricTemplatePropagator: command already queued or duplicate key collision for sibling device', [
                    'sibling_device_id' => $sibling->id,
                    'member_id' => $member->id,
                    'template_id' => $template->id,
                ]);
            } catch (BiometricConsentRequiredException $e) {
                $failedDeviceIds[] = $sibling->id;
                $failedDeviceNames[] = $sibling->device_name;

                Log::warning('BiometricTemplatePropagator: sibling fan-out blocked by missing biometric consent', [
                    'sibling_device_id' => $sibling->id,
                    'member_id' => $member->id,
                    'template_id' => $template->id,
                ]);
            } catch (Throwable $e) {
                $failedDeviceIds[] = $sibling->id;
                $failedDeviceNames[] = $sibling->device_name;

                Log::error('BiometricTemplatePropagator: failed to queue sibling user_add command', [
                    'sibling_device_id' => $sibling->id,
                    'member_id' => $member->id,
                    'template_id' => $template->id,
                    'error_type' => get_class($e),
                ]);

                $this->persistFailedCommand($sibling, $member, $template, $commandPin, $e);
            }
        }

        return new BiometricPropagationResult(
            queuedCount: $queuedCount,
            alreadyProvisionedCount: $alreadyProvisionedCount,
            failedDeviceIds: $failedDeviceIds,
            failedDeviceNames: $failedDeviceNames,
            totalSiblings: $siblingDevices->count()
        );
    }

    /**
     * Demote a live explicit erasure request on the sibling to `Superseded`.
     *
     * This is an explicit erasure request — member- or operator-initiated — overtaken by a
     * re-enrolment, so it is recorded as such and retained for audit rather than being an
     * implicit consequence of a column write.
     *
     * The fan-out only discharges the origin's own provenance class: the superseder is
     * handed `$template->provenance()`, so a hardware origin cannot demote a cloud-backed
     * erasure on the sibling (nor the reverse). On ADR-004's dual-mode device the two share
     * a PIN but are two credentials, and `Superseded` strands whichever one is demoted.
     */
    protected function supersedePendingDeletionSiblings(
        BiometricDevice $sibling,
        GymMember $member,
        BiometricTemplate $template
    ): void {
        $this->superseder->supersedeErasureOverrides(
            $sibling->gym_id,
            $member,
            $sibling->id,
            $template->biometric_type->value,
            $template->provenance()
        );
    }

    /**
     * Create or refresh the sibling's row so it tracks physical device state as `Propagated`.
     *
     * A row in `Deleted`, `PendingDeletion`, `Superseded` or `DeletionUnresolvable` is never
     * resurrected; a fresh row is created instead so the erasure audit trail survives. A row
     * flagged `is_erasure_obligation` is excluded for the same reason: it is a machine-written
     * erasure obligation, not an enrolment a new fan-out replaces. Repurposing it would destroy
     * the only reference to an un-erased credential and turn a compliance record into a live
     * template.
     *
     * The candidate row must also share the origin's provenance. `provider_type` and
     * `template_ref` are copied from the origin onto the sibling, so matching a row of a
     * different class would rewrite a live cloud reference as a hardware one — erasing the
     * handle the provider credential is addressed by. A hardware and a cloud credential on the
     * same PIN are two credentials (ADR-004), so the other one is left live and gets its own
     * sibling row.
     */
    protected function upsertSiblingTemplate(
        BiometricDevice $sibling,
        GymMember $member,
        BiometricTemplate $template
    ): BiometricTemplate {
        $existingSiblingTemplate = BiometricTemplate::query()
            ->where('gym_member_id', $member->id)
            ->where('gym_id', $sibling->gym_id)
            ->where('enrolled_by_device_id', $sibling->id)
            ->where('biometric_type', $template->biometric_type)
            ->where('is_erasure_obligation', false)
            ->when(
                $template->hasCloudProvider(),
                fn (Builder $q) => $q->cloudBacked(),
                fn (Builder $q) => $q->hardwareBacked(),
            )
            ->whereIn('sync_status', BiometricTemplateSyncStatusEnum::liveCredentialStates())
            ->first();

        if ($existingSiblingTemplate === null) {
            return BiometricTemplate::query()->create([
                'gym_member_id' => $member->id,
                'gym_id' => $sibling->gym_id,
                'enrolled_by_device_id' => $sibling->id,
                'biometric_type' => $template->biometric_type,
                'provider_type' => $template->provider_type,
                'template_ref' => $template->template_ref,
                'sync_status' => BiometricTemplateSyncStatusEnum::Propagated,
                'is_active' => true,
                'enrolled_at' => null,
                'hardware_confirmed_at' => null,
            ]);
        }

        $updateData = [
            'provider_type' => $template->provider_type,
            'template_ref' => $template->template_ref,
            'is_active' => true,
        ];

        if ($existingSiblingTemplate->sync_status !== BiometricTemplateSyncStatusEnum::Synced) {
            $updateData['sync_status'] = BiometricTemplateSyncStatusEnum::Propagated;
            $updateData['enrolled_at'] = null;
            $updateData['hardware_confirmed_at'] = null;
        }

        $existingSiblingTemplate->update($updateData);

        return $existingSiblingTemplate;
    }

    /**
     * Persist a `FAILED` command so the Filament retry action has a record to act on.
     *
     * The exception message is deliberately reduced to its class: `command_payload` is a
     * durable, non-redacted column and a `QueryException` raised while binding a `user_add`
     * payload embeds the member's name.
     */
    protected function persistFailedCommand(
        BiometricDevice $sibling,
        GymMember $member,
        BiometricTemplate $template,
        string $commandPin,
        Throwable $e
    ): void {
        try {
            AdmsDeviceCommand::query()->create([
                'gym_id' => $sibling->gym_id,
                'device_id' => $sibling->id,
                'command_type' => 'user_add',
                'command_payload' => [
                    'Pin' => $commandPin,
                    'Name' => $member->name,
                    'failure_reason' => 'unhandled_' . class_basename($e),
                ],
                'status' => AdmsCommandStatusEnum::FAILED,
                'idempotency_key' => null,
            ]);
        } catch (Throwable $secondaryException) {
            Log::warning('BiometricTemplatePropagator: failed to persist FAILED command row', [
                'sibling_device_id' => $sibling->id,
                'member_id' => $member->id,
                'template_id' => $template->id,
                'error_type' => get_class($secondaryException),
            ]);
        }
    }
}
