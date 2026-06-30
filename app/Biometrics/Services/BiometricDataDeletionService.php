<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\TemplateRef;
use App\Models\BiometricConsent;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\Activitylog\Facades\Activity;

class BiometricDataDeletionService
{
    public function __construct(
        private BiometricProviderRegistry $registry
    ) {}

    /**
     * Delete all biometric data for a gym member (GDPR compliance)
     *
     * This method is idempotent and resilient:
     * - Skips already inactive templates
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

        // Step 2: Call provider->deleteTemplate() for each
        foreach ($templates as $template) {
            // Skip already inactive templates (idempotent)
            if (! $template->is_active) {
                continue;
            }

            try {
                $provider = $this->registry->resolve($template->provider_type);

                $templateRef = new TemplateRef(
                    provider: $template->provider_type,
                    externalId: $template->template_ref,
                    meta: []
                );

                $result = $provider->deleteTemplate($templateRef);

                // Only deactivate local template AFTER successful provider deletion
                if ($result === true) {
                    $template->is_active = false;
                    $template->save();
                } else {
                    // Provider returned false - log and continue but don't deactivate
                    Log::warning('Provider did not confirm template deletion', [
                        'template_id' => $template->id,
                        'external_id' => $template->template_ref,
                        'provider' => $template->provider_type,
                    ]);
                }
            } catch (Exception $e) {
                // Catch exception per template and accumulate error
                // This allows processing to continue for remaining templates
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
        Activity::on($gymMember)->log('Biometric data deleted per GDPR request');

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
}
