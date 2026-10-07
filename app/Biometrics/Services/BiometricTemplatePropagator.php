<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\Exceptions\BiometricConsentRequiredException;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Biometrics\ValueObjects\BiometricPropagationResult;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
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
     * @throws BiometricConsentRequiredException when consent is absent at fan-out time
     */
    public function propagateToSiblingDevices(
        BiometricDevice $originatingDevice,
        GymMember $member,
        BiometricTemplate $template
    ): BiometricPropagationResult {
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
     * Demote a live GDPR erasure request on the sibling to `Superseded`.
     *
     * This is an operator override of a member's Art. 17 request, so it is recorded as
     * such and retained for audit rather than being an implicit consequence of a
     * column write.
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
            $template->biometric_type->value
        );
    }

    /**
     * Create or refresh the sibling's row so it tracks physical device state as `Propagated`.
     *
     * A row in `Deleted`, `PendingDeletion` or `Superseded` is never resurrected; a fresh
     * row is created instead so the erasure audit trail survives.
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
            ->whereNotIn('sync_status', [
                BiometricTemplateSyncStatusEnum::Deleted,
                BiometricTemplateSyncStatusEnum::PendingDeletion,
                BiometricTemplateSyncStatusEnum::Superseded,
            ])
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
