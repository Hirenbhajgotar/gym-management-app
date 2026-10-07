<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\TemplateRef;
use App\Enums\BiometricDeProvisionOutcome;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\BiometricConsent;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BiometricDataDeletionService
{
    public function __construct(
        private BiometricProviderRegistry $registry,
        private BiometricDeProvisioner $deProvisioner
    ) {}

    /**
     * Delete all biometric data for a gym member (GDPR compliance)
     *
     * This method is idempotent and resilient:
     * - Skips already inactive and fully deleted templates
     * - Catches exceptions per template and continues processing
     * - Throws aggregated exception after loop if any failures occurred
     *
     * For hardware-backed templates, success means the erasure obligation is durably
     * recorded, not that a terminal has confirmed removal. Templates whose hardware
     * erasure is still pending are reported in the activity log properties.
     *
     * Note: Individual template deletions are processed independently and not wrapped
     * in a single encompassing DB transaction. This is an intentional architectural
     * decision to prevent long-lived database locks and lock-wait timeouts while external
     * network I/O calls to cloud biometric providers (e.g. AWS Rekognition, Azure Face)
     * are in flight. If any template de-provisioning fails, errors are accumulated,
     * consent and attendance records remain untouched, and an aggregated exception is thrown.
     *
     * @throws RuntimeException when template deletion fails - allows listener retry
     */
    public function deleteForGymMember(GymMember $gymMember): void
    {
        $templates = BiometricTemplate::query()
            ->with(['enrolledByDevice'])
            ->where('gym_member_id', $gymMember->id)
            ->get();

        $errors = [];
        $pendingHardwareErasures = [];

        foreach ($templates as $template) {
            if (! $template->is_active && $template->sync_status === BiometricTemplateSyncStatusEnum::Deleted) {
                continue;
            }

            try {
                $success = $this->deleteSingleTemplate($template);

                if (! $success) {
                    $errors[] = [
                        'template_id' => $template->id,
                        'external_id' => BiometricLogRedactor::mask($template->template_ref),
                        'provider' => $template->provider_type,
                        'error' => 'Provider did not confirm template deletion',
                    ];
                } elseif ($template->refresh()->sync_status === BiometricTemplateSyncStatusEnum::PendingDeletion) {
                    $pendingHardwareErasures[] = [
                        'template_id' => $template->id,
                        'device_id' => $template->enrolled_by_device_id,
                    ];
                }
            } catch (Throwable $e) {
                $errors[] = [
                    'template_id' => $template->id,
                    'external_id' => BiometricLogRedactor::mask($template->template_ref),
                    'provider' => $template->provider_type,
                    'error' => 'deprovisioning_failed: ' . $e::class,
                ];

                Log::warning('Biometric erasure de-provisioning failed for template', [
                    'template_id' => $template->id,
                    'error_type' => $e::class,
                ]);
            }
        }

        if (filled($errors)) {
            throw new RuntimeException(
                'Biometric data deletion failed for ' . count($errors) . ' template(s). See logs for details.',
                0
            );
        }

        DB::transaction(function () use ($gymMember): void {
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

            BiometricConsent::query()->where('gym_member_id', $gymMember->id)->delete();
        });

        activity('biometric')
            ->performedOn($gymMember)
            ->withProperties([
                'gym_member_id' => $gymMember->id,
                'pending_hardware_erasures' => $pendingHardwareErasures,
            ])
            ->log(
                $pendingHardwareErasures === []
                    ? 'Biometric data deletion completed per GDPR request'
                    : 'Biometric data deletion requested per GDPR request; hardware erasure pending terminal confirmation'
            );
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
     *
     * Returns true only when the erasure obligation is durably recorded. For hardware-backed
     * templates that means the request is parked at `PendingDeletion` and will be revisited
     * by the nightly reconciler; it does not mean the terminal has confirmed removal.
     */
    public function deleteSingleTemplate(BiometricTemplate $template): bool
    {
        if (! $template->is_active && $template->sync_status === BiometricTemplateSyncStatusEnum::Deleted) {
            return true;
        }

        if ($template->hasCloudProvider()) {
            return $this->deleteCloudTemplate($template);
        }

        if ($template->enrolled_by_device_id === null) {
            $template->update([
                'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
                'is_active' => false,
            ]);

            return true;
        }

        $outcome = $this->deProvisioner->deprovision($template);

        if ($outcome === BiometricDeProvisionOutcome::NotDelivered) {
            return true;
        }

        if ($outcome === BiometricDeProvisionOutcome::Queued) {
            Log::info('deleteSingleTemplate: queued hardware user_delete', [
                'template_id' => $template->id,
                'device_id' => $template->enrolled_by_device_id,
                'outcome' => $outcome->value,
            ]);
        }

        return true;
    }

    /**
     * Cloud provider templates are erased through the provider API, which is authoritative.
     */
    protected function deleteCloudTemplate(BiometricTemplate $template): bool
    {
        if ($template->template_ref !== null) {
            $provider = $this->registry->resolve($template->provider_type);
            $templateRef = $this->resolveTemplateRef($template);

            $result = $provider->deleteTemplate($templateRef);

            if ($result !== true) {
                $template->update([
                    'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
                    'is_active' => false,
                ]);

                Log::warning('Provider did not confirm template deletion', [
                    'template_id' => $template->id,
                    'external_id' => BiometricLogRedactor::mask($template->template_ref),
                    'provider' => $template->provider_type,
                ]);

                return false;
            }
        }

        $template->update([
            'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
            'is_active' => false,
        ]);

        return true;
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
            } catch (Throwable $e) {
                Log::warning('BiometricDataDeletionService: failed to parse JSON template ref; falling back to raw ref', [
                    'template_id' => $template->id,
                    'error_type' => $e::class,
                ]);
            }
        }

        return new TemplateRef(
            provider: $template->provider_type,
            externalId: $ref,
            meta: []
        );
    }
}
