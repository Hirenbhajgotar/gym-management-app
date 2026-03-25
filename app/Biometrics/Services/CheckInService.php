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

        // Step 3: Load BiometricTemplate for this gym and biometric type
        $template = BiometricTemplate::where('gym_id', $device->gym_id)
            ->where('biometric_type', $sample->type)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return CheckInResult::denied('no_template_enrolled');
        }

        // Step 4: Get gym member from template
        $gymMember = $template->gymMember;

        if (! $gymMember) {
            return CheckInResult::denied('member_not_found');
        }

        // Step 5: Provider verify
        $verificationResult = $provider->verify($sample, \App\Biometrics\DTOs\TemplateRef::fromJson($template->template_ref));

        $confidenceThreshold = config('biometrics.confidence_threshold', 0.80);

        if (! $verificationResult->matched || $verificationResult->confidence < $confidenceThreshold) {
            return CheckInResult::denied('verification_failed');
        }

        // Step 6: FusionEngine - merge if multi-modal (simplified for single modal)
        $finalResult = $verificationResult;

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
            'confidence_score' => $finalResult->confidence,
            'liveness_score' => $finalResult->livenessScore,
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
