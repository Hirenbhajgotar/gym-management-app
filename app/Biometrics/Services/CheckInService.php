<?php

namespace App\Biometrics\Services;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\CheckInResult;
use App\Enums\AttendanceStatus;
use App\Events\MemberCheckedIn;
use App\Events\MemberCheckedOut;
use App\Events\SuspiciousCheckInDetected;
use App\Models\Attendances;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;

class CheckInService
{
    public function __construct(
        protected BiometricProviderRegistry $registry,
        protected AntiSpoofGuard $antiSpoofGuard,
        protected MembershipGuard $membershipGuard,
        protected FraudDetector $fraudDetector,
        protected FusionEngine $fusionEngine
    ) {}

    public function processCheckIn(BiometricSample $sample, BiometricDevice $device): CheckInResult
    {
        // Step 1: Resolve provider
        $provider = $this->registry->resolve($device->provider_type, $device->provider_config ?? []);

        // Step 2: AntiSpoofGuard - liveness detection
        if (! $this->antiSpoofGuard->check($sample, $provider)) {
            return CheckInResult::denied('liveness_failed');
        }

        // Step 3: Load ALL BiometricTemplates for this gym and biometric type
        $templates = BiometricTemplate::where('gym_id', $device->gym_id)
            ->where('biometric_type', $sample->type)
            ->where('is_active', true)
            ->with('gymMember')
            ->get();

        if ($templates->isEmpty()) {
            return CheckInResult::denied('no_template_enrolled');
        }

        // Step 4: Try to match against each template
        $matchedTemplate = null;
        $confidenceThreshold = config('biometrics.confidence_threshold', 0.80);

        foreach ($templates as $template) {
            $templateRef = \App\Biometrics\DTOs\TemplateRef::fromJson($template->template_ref);
            $verificationResult = $provider->verify($sample, $templateRef);

            if ($verificationResult->matched && $verificationResult->confidence >= $confidenceThreshold) {
                $matchedTemplate = $template;
                break; // Found a match, stop iterating
            }
        }

        if (! $matchedTemplate) {
            return CheckInResult::denied('verification_failed');
        }

        // Step 5: Get gym member from matched template
        $gymMember = $matchedTemplate->gymMember;

        if (! $gymMember) {
            return CheckInResult::denied('member_not_found');
        }

        // Step 6: Provider verify succeeded, continue with checks
        $gymMember->refresh();

        // Step 7: MembershipGuard - check membership, payment, freeze, consent
        $guardResult = $this->membershipGuard->canCheckIn($gymMember);

        if (! $guardResult->allowed) {
            return CheckInResult::denied($guardResult->reason ?? 'access_denied');
        }

        // Step 8: FraudDetector - duplicate check
        $isSuspicious = false;

        if ($this->fraudDetector->isDuplicate($gymMember)) {
            $isSuspicious = true;
        }

        // Step 9: Suspicious velocity check
        if ($this->fraudDetector->isSuspiciousVelocity($gymMember)) {
            // Fire event to alert staff
            broadcast(new SuspiciousCheckInDetected($gymMember, $device, 'suspicious_velocity'));
        }

        // Step 10: Create Attendance record
        $attendance = $gymMember->attendances()->create([
            'gym_id' => $device->gym_id,
            'check_in_time' => now(),
            'status' => AttendanceStatus::Present,
            'check_in_method' => 'biometric',
            'device_id' => $device->id,
            'biometric_type' => $sample->type,
            'provider_type' => $device->provider_type,
            'confidence_score' => $verificationResult->confidence ?? null,
            'liveness_score' => $verificationResult->livenessScore ?? null,
            'check_in_result' => $isSuspicious ? 'suspicious' : 'granted',
            'check_in_ip' => request()->ip(),
        ]);

        // Step 11: Fire MemberCheckedIn event
        broadcast(new MemberCheckedIn($attendance, $device));

        // Step 12: Return result
        if ($isSuspicious) {
            return CheckInResult::suspicious('duplicate_check_in', $attendance);
        }

        return CheckInResult::granted($attendance);
    }

    public function processCheckOut(GymMember $gymMember, BiometricDevice $device): CheckInResult
    {
        // Find active attendance
        $attendance = Attendances::where('attendanceable_id', $gymMember->id)
            ->where('attendanceable_type', GymMember::class)
            ->where('gym_id', $device->gym_id)
            ->active()
            ->first();

        if (! $attendance) {
            return CheckInResult::denied('no_active_check_in');
        }

        // Update checkout
        $attendance->update([
            'check_out_time' => now(),
            'status' => AttendanceStatus::Completed,
        ]);

        // Fire MemberCheckedOut event
        broadcast(new MemberCheckedOut($attendance, $device));

        return CheckInResult::granted($attendance);
    }
}
