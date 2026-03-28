<?php

namespace App\Biometrics\Services;

use App\Models\BiometricConsent;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Facades\Activity;

class BiometricDataDeletionService
{
    /**
     * Delete all biometric data for a gym member (GDPR compliance)
     *
     * @throws \Exception when provider deletion fails - allows listener retry
     */
    public function deleteForGymMember(GymMember $gymMember): void
    {
        // Step 1: Load all BiometricTemplate for this member
        $templates = BiometricTemplate::where('gym_member_id', $gymMember->id)->get();

        // Step 2: Call provider->deleteTemplate() for each
        // Provider errors are NOT suppressed - rethrow to trigger listener retry
        foreach ($templates as $template) {
            $provider = $this->registry->resolve($template->provider_type);

            $templateRef = new \App\Biometrics\DTOs\TemplateRef(
                provider: $template->provider_type,
                externalId: $template->template_ref,
                meta: []
            );

            // This will throw if provider deletion fails - allowing retry
            $result = $provider->deleteTemplate($templateRef);

            // Only deactivate local template AFTER successful provider deletion
            if ($result === true) {
                $template->is_active = false;
                $template->save();
            } else {
                // Provider returned false - log and continue but don't deactivate
                // This indicates provider couldn't delete (not an error, but not success)
                Log::warning('Provider did not confirm template deletion', [
                    'template_id' => $template->id,
                    'external_id' => $template->template_ref,
                    'provider' => $template->provider_type,
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
        BiometricConsent::where('gym_member_id', $gymMember->id)->delete();

        // Step 5: Log via Spatie activity log
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
