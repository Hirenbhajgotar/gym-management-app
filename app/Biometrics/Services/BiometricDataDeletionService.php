<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\TemplateRef;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\Activitylog\Facades\Activity;
use Throwable;

class BiometricDataDeletionService
{
    public function __construct(
        private BiometricProviderRegistry $registry
    ) {}

    /**
     * Delete all biometric data for a gym member (GDPR compliance)
     *
     * This method is idempotent and resilient:
     * - Skips already inactive and fully deleted templates
     * - Catches exceptions per template and continues processing
     * - Throws aggregated exception after loop if any failures occurred
     *
     * @throws Exception when provider deletion fails - allows listener retry
     */
    public function deleteForGymMember(GymMember $gymMember): void
    {
        // Step 1: Load all BiometricTemplate for this member
        $templates = BiometricTemplate::query()->where('gym_member_id', $gymMember->id)->get();

        // Accumulate errors for throwing after loop
        $errors = [];

        // Step 2: Delete each template through the unified deletion lifecycle
        foreach ($templates as $template) {
            // Skip already inactive and confirmed deleted templates (idempotent)
            if (! $template->is_active && $template->sync_status === BiometricTemplateSyncStatusEnum::Deleted) {
                continue;
            }

            try {
                $success = $this->deleteSingleTemplate($template);

                if (! $success) {
                    $errors[] = [
                        'template_id' => $template->id,
                        'external_id' => $template->template_ref,
                        'provider' => $template->provider_type,
                        'error' => 'Provider did not confirm template deletion',
                    ];
                }
            } catch (Throwable $e) {
                $errors[] = [
                    'template_id' => $template->id,
                    'external_id' => $template->template_ref,
                    'provider' => $template->provider_type,
                    'error' => $e->getMessage(),
                ];

                Log::warning('Provider deletion failed for template', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Step 3: Anonymize biometric attendance columns
        // Keep check_in_time and status for attendance statistics
        $gymMember->attendances()
            ->biometric()
            ->update([
                'check_in_method' => 'anonymized',
                'biometric_type' => null,
                'provider_type' => null,
                'confidence_score' => null,
                'liveness_score' => null,
                'fusion_score' => null,
                'check_in_result' => null,
                'device_id' => null,
            ]);

        // Step 4: Delete BiometricConsent records
        BiometricConsent::query()->where('gym_member_id', $gymMember->id)->delete();

        // Step 5: Log via Spatie activity log
        Activity::on($gymMember)->log('Biometric data deletion initiated per GDPR request');

        // Step 6: Throw aggregated exception if any errors occurred
        // This triggers the listener retry mechanism
        if (filled($errors)) {
            throw new RuntimeException(
                'Biometric data deletion failed for ' . count($errors) . ' template(s). See logs for details.',
                0
            );
        }
    }

    /**
     * Revoke consent and optionally delete biometric data
     */
    public function revokeConsent(GymMember $gymMember, bool $deleteData = false): void
    {
        // Revoke all consents
        BiometricConsent::query()->where('gym_member_id', $gymMember->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($deleteData) {
            $this->deleteForGymMember($gymMember);
        }
    }

    /**
     * Delete an individual biometric template across cloud providers or hardware devices.
     */
    public function deleteSingleTemplate(BiometricTemplate $template): bool
    {
        if (! $template->is_active && $template->sync_status === BiometricTemplateSyncStatusEnum::Deleted) {
            return true;
        }

        if (in_array($template->provider_type, ['device_native', 'adms', 'hikvision', null], true)) {
            $device = $template->enrolledByDevice;
            if ($device && $device->adms_enabled) {
                $pin = $template->resolvedCommandPin();

                $existingCommand = AdmsDeviceCommand::query()
                    ->where('device_id', $device->id)
                    ->where('command_type', 'user_delete')
                    ->where('command_payload->Pin', $pin)
                    ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
                    ->first();

                if (! $existingCommand) {
                    try {
                        AdmsDeviceCommand::query()->create([
                            'gym_id' => $device->gym_id,
                            'device_id' => $device->id,
                            'command_type' => 'user_delete',
                            'command_payload' => ['Pin' => $pin],
                            'idempotency_key' => "user_delete:{$device->id}:{$pin}:{$template->id}",
                            'status' => AdmsCommandStatusEnum::PENDING,
                        ]);
                    } catch (UniqueConstraintViolationException) {
                        // In-flight command already exists
                    }
                }

                $template->sync_status = BiometricTemplateSyncStatusEnum::PendingDeletion;
            } elseif ($device) {
                $template->sync_status = BiometricTemplateSyncStatusEnum::PendingDeletion;
            } else {
                $template->sync_status = BiometricTemplateSyncStatusEnum::Deleted;
            }

            $template->is_active = false;
            $template->save();

            return true;
        }

        $provider = $this->registry->resolve($template->provider_type);
        $templateRef = $this->resolveTemplateRef($template);

        $result = $provider->deleteTemplate($templateRef);

        if ($result === true) {
            $template->is_active = false;
            $template->sync_status = BiometricTemplateSyncStatusEnum::Deleted;
            $template->save();

            return true;
        }

        $template->is_active = false;
        $template->sync_status = BiometricTemplateSyncStatusEnum::PendingDeletion;
        $template->save();

        Log::warning('Provider did not confirm template deletion', [
            'template_id' => $template->id,
            'external_id' => BiometricLogRedactor::mask((string) $template->template_ref),
            'provider' => $template->provider_type,
        ]);

        return false;
    }

    /**
     * Safely resolve TemplateRef DTO from template record.
     */
    protected function resolveTemplateRef(BiometricTemplate $template): TemplateRef
    {
        $ref = (string) $template->template_ref;
        if (str_starts_with($ref, '{') && str_ends_with($ref, '}')) {
            try {
                return TemplateRef::fromJson($ref);
            } catch (Throwable) {
                // fallback to plain string
            }
        }

        return new TemplateRef(
            provider: $template->provider_type,
            externalId: $ref,
            meta: []
        );
    }
}
