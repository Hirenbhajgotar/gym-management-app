<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeProvisionOutcome;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricTemplate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Single authority for hardware de-provisioning of a biometric template.
 *
 * Every producer of a hardware erasure delegates here — the GDPR deletion service, the nightly
 * `biometrics:reconcile-pending-deletions` reconciler and the expired-member cleanup — so
 * sibling suppression, tenant scoping and retry policy cannot drift between them.
 *
 * ADMS exposes only `DATA DELETE user Pin=N`, which erases the whole credential for a PIN. A
 * member holding both a fingerprint and a face template on one device shares a single PIN and
 * therefore a single hardware credential, so template-scoped erasure is not expressible and
 * neither is a template-scoped idempotency key. `Deleted` is written in exactly two
 * circumstances: the template provably never reached the hardware, or a terminal confirmed the
 * delete. Every other outcome parks the row at `PendingDeletion` for the reconciler.
 */
class BiometricDeProvisioner
{
    /**
     * Request hardware erasure for a device-bound template.
     *
     * Commits `PendingDeletion` unless the template provably never reached the hardware,
     * in which case `Deleted` is written because there is nothing left to erase.
     */
    public function deprovision(BiometricTemplate $template): BiometricDeProvisionOutcome
    {
        $pin = $template->resolvedCommandPin();

        // Cancel first: a queued or in-flight user_add would otherwise be delivered after the
        // user_delete and re-create the credential the erasure is trying to remove.
        if ($template->enrolled_by_device_id !== null) {
            $cancelled = AdmsDeviceCommand::cancelInFlightFor($template->enrolled_by_device_id, $pin);

            if ($cancelled > 0) {
                Log::info('BiometricDeProvisioner: cancelled in-flight user_add for this PIN', [
                    'template_id' => $template->id,
                    'device_id' => $template->enrolled_by_device_id,
                    'cancelled_count' => $cancelled,
                ]);
            }
        }

        if ($template->sync_status === BiometricTemplateSyncStatusEnum::Pending
            && ! $template->wasDeliveredToHardware()) {
            $template->update([
                'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
                'is_active' => false,
            ]);

            return BiometricDeProvisionOutcome::NotDelivered;
        }

        if ($template->hasActiveSiblingOnSameDevice()) {
            Log::info('BiometricDeProvisioner: hardware credential retained for a sibling template on the same device', [
                'template_id' => $template->id,
                'gym_id' => $template->gym_id,
                'member_id' => $template->gym_member_id,
                'device_id' => $template->enrolled_by_device_id,
            ]);

            $this->parkAsPendingDeletion($template);

            return BiometricDeProvisionOutcome::DeferredToSibling;
        }

        $device = $template->enrolledByDevice;

        // The template and the device must belong to the same gym, otherwise the queued
        // command would be attributed to the wrong tenant.
        if ($device === null || $device->gym_id !== $template->gym_id) {
            Log::warning('BiometricDeProvisioner: device record missing or belongs to another gym; hardware clearance cannot be verified', [
                'template_id' => $template->id,
                'gym_id' => $template->gym_id,
                'enrolled_by_device_id' => $template->enrolled_by_device_id,
                'device_gym_id' => $device?->gym_id,
            ]);

            $this->parkAsPendingDeletion($template);

            return BiometricDeProvisionOutcome::DeviceUnavailable;
        }

        $this->parkAsPendingDeletion($template);

        if (! $device->adms_enabled || $device->status !== BiometricDeviceStatusEnum::Active) {
            Log::info('BiometricDeProvisioner: device not ADMS-enabled or not active; awaiting terminal online', [
                'template_id' => $template->id,
                'device_id' => $device->id,
                'status' => $device->status->value,
            ]);

            return BiometricDeProvisionOutcome::DeviceUnavailable;
        }

        return $this->ensureUserDeleteCommand($template, $device->id, $pin);
    }

    /**
     * Ensure exactly one live `user_delete` command exists for this PIN on this device.
     *
     * The idempotency key is PIN-scoped because the command is PIN-scoped: the UNIQUE index on
     * `idempotency_key` is what actually enforces the invariant, and a template id in the key
     * would let two templates sharing a PIN each insert their own command. A `FAILED` row below
     * the attempt cap is re-driven in place so its retained key keeps the command deduplicated
     * and its attempt counter keeps climbing.
     */
    protected function ensureUserDeleteCommand(BiometricTemplate $template, int $deviceId, string $pin): BiometricDeProvisionOutcome
    {
        $hasLiveCommand = AdmsDeviceCommand::query()
            ->where('device_id', $deviceId)
            ->where('command_type', 'user_delete')
            ->where('command_payload->Pin', $pin)
            ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
            ->exists();

        if ($hasLiveCommand) {
            return BiometricDeProvisionOutcome::AlreadyQueued;
        }

        $deleteKey = AdmsCommandKey::buildUserDelete($deviceId, $pin);

        $existingFailed = AdmsDeviceCommand::query()
            ->where('device_id', $deviceId)
            ->where('idempotency_key', $deleteKey)
            ->where('status', AdmsCommandStatusEnum::FAILED)
            ->first();

        if ($existingFailed !== null) {
            if ($existingFailed->attempt_count >= AdmsCommandKey::maxAttempts()) {
                Log::error('BiometricDeProvisioner: hardware erase delivery budget exhausted; manual intervention required', [
                    'template_id' => $template->id,
                    'gym_id' => $template->gym_id,
                    'member_id' => $template->gym_member_id,
                    'device_id' => $deviceId,
                    'command_id' => $existingFailed->id,
                    'attempt_count' => $existingFailed->attempt_count,
                    'attempt_cap' => AdmsCommandKey::maxAttempts(),
                    'device_return_code' => $existingFailed->device_return_code,
                ]);

                return BiometricDeProvisionOutcome::Exhausted;
            }

            $existingFailed->update([
                'status' => AdmsCommandStatusEnum::PENDING,
                'sent_at' => null,
                'acknowledged_at' => null,
                'leased_at' => null,
                'expires_at' => null,
                'device_return_code' => null,
            ]);

            return BiometricDeProvisionOutcome::ReDriven;
        }

        try {
            AdmsDeviceCommand::query()->create([
                'gym_id' => $template->gym_id,
                'device_id' => $deviceId,
                'command_type' => 'user_delete',
                'command_payload' => ['Pin' => $pin],
                'idempotency_key' => $deleteKey,
                'status' => AdmsCommandStatusEnum::PENDING,
            ]);

            return BiometricDeProvisionOutcome::Queued;
        } catch (UniqueConstraintViolationException $e) {
            Log::warning('BiometricDeProvisioner: duplicate delete command collision', [
                'template_id' => $template->id,
                'device_id' => $deviceId,
                'idempotency_key' => $deleteKey,
            ]);

            return BiometricDeProvisionOutcome::AlreadyQueued;
        }
    }

    protected function parkAsPendingDeletion(BiometricTemplate $template): void
    {
        $template->update([
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);
    }
}
