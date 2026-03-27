<?php

namespace App\Biometrics\Services;

use App\Biometrics\BiometricProviderRegistry;
use App\Models\BiometricConsent;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Spatie\Activitylog\Facades\Activity;

class BiometricDataDeletionService
{
    public function __construct(
        protected BiometricProviderRegistry $registry
    ) {}

    /**
     * Delete all biometric data for a gym member (GDPR compliance)
     */
    public function deleteForGymMember(GymMember $gymMember): void
    {
        // Step 1: Load all BiometricTemplate for this member
        $templates = BiometricTemplate::where('gym_member_id', $gymMember->id)->get();

        // Step 2: Call provider->deleteTemplate() for each
        foreach ($templates as $template) {
            try {
                $provider = $this->registry->resolve($template->provider_type);

                $templateRef = new \App\Biometrics\DTOs\TemplateRef(
                    provider: $template->provider_type,
                    externalId: $template->template_ref,
                    meta: []
                );

                $provider->deleteTemplate($templateRef);
            } catch (\Exception $e) {
                // Log error but continue with deletion
                report($e);
            }

            // Step 3: Soft-delete or deactivate template
            $template->is_active = false;
            $template->save();
        }

        // Step 4: Anonymize biometric attendance columns
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

        // Step 5: Delete BiometricConsent records
        BiometricConsent::where('gym_member_id', $gymMember->id)->delete();

        // Step 6: Log via Spatie activity log
        Activity::on($gymMember)->log('Biometric data deleted per GDPR request');
    }

    /**
     * Revoke consent and optionally delete biometric data
     */
    public function revokeConsent(GymMember $gymMember, bool $deleteData = false): void
    {
        // Revoke all consents
        BiometricConsent::where('gym_member_id', $gymMember->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($deleteData) {
            $this->deleteForGymMember($gymMember);
        }
    }
}
