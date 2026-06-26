<?php

namespace App\Biometrics\Services;

use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\CheckInResult;
use App\Biometrics\DTOs\DoorControlResult;
use App\Biometrics\DTOs\VerificationResult;
use App\Enums\AttendanceStatus;
use App\Events\MemberCheckedIn;
use App\Events\MemberCheckedOut;
use App\Events\SuspiciousCheckInDetected;
use App\Models\Attendances;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Carbon\Carbon;

class CheckInService
{
    public function __construct(
        protected BiometricProviderRegistry $registry,
        protected AntiSpoofGuard $antiSpoofGuard,
        protected MembershipGuard $membershipGuard,
        protected FraudDetector $fraudDetector,
        protected FusionEngine $fusionEngine,
        protected DoorControlService $doorControlService
    ) {}

    public function processCheckIn(BiometricSample $sample, BiometricDevice $device): CheckInResult
    {
        // ─────────────────────────────────────────────
        // STEP 1: Resolve provider
        // ─────────────────────────────────────────────
        $provider = $this->registry->resolve($device->provider_type, $device->provider_config ?? []);

        // ─────────────────────────────────────────────
        // STEP 2: Liveness check
        // ─────────────────────────────────────────────
        $livenessScore = $this->antiSpoofGuard->check($sample, $provider);
        if ($livenessScore < config('biometrics.liveness_threshold', 0.75)) {
            return $this->denyAndLog($device, null, 'liveness_failed');
        }

        // ─────────────────────────────────────────────
        // STEP 3: Load biometric template
        // ─────────────────────────────────────────────
        $templates = BiometricTemplate::where('gym_id', $device->gym_id)
            ->where('biometric_type', $sample->type)
            ->where('is_active', true)
            ->with('gymMember')
            ->get();

        if ($templates->isEmpty()) {
            return $this->denyAndLog($device, null, 'no_template_enrolled');
        }

        // ─────────────────────────────────────────────
        // STEP 4: Biometric verification
        // ─────────────────────────────────────────────
        $matchedTemplate = null;
        $confidenceThreshold = config('biometrics.confidence_threshold', 0.80);
        $verificationResult = null;

        foreach ($templates as $template) {
            $templateRef = TemplateRef::fromJson($template->template_ref);
            $verificationResult = $provider->verify($sample, $templateRef);

            if ($verificationResult->matched && $verificationResult->confidence >= $confidenceThreshold) {
                $matchedTemplate = $template;
                break;
            }
        }

        if (! $matchedTemplate) {
            return $this->denyAndLog($device, null, 'verification_failed');
        }

        // ─────────────────────────────────────────────
        // STEP 5: Get gym member
        // ─────────────────────────────────────────────
        $gymMember = $matchedTemplate->gymMember;

        if (! $gymMember) {
            return $this->denyAndLog($device, null, 'member_not_found');
        }

        // ─────────────────────────────────────────────
        // STEP 6: Membership guard
        // ─────────────────────────────────────────────
        $gymMember->refresh(); // Always fresh — never use cached membership status
        $guardResult = $this->membershipGuard->canCheckIn($gymMember);

        if (! $guardResult->allowed) {
            return $this->denyAndLog($device, $gymMember, $guardResult->reason);
        }

        // ─────────────────────────────────────────────
        // STEP 7: Fraud detection
        // ─────────────────────────────────────────────
        $isDuplicate = $this->fraudDetector->isDuplicate($gymMember);

        if ($this->fraudDetector->isSuspiciousVelocity($gymMember)) {
            broadcast(new SuspiciousCheckInDetected($gymMember, $device, 'suspicious_velocity'));
        }

        // ─────────────────────────────────────────────
        // STEP 8: Branch on device purpose
        // ─────────────────────────────────────────────

        // ── Branch A: Attendance Only ──────────────────
        if ($device->isAttendanceOnly()) {
            $attendance = $this->createAttendance(
                gymMember: $gymMember,
                device: $device,
                verificationResult: $verificationResult,
                livenessScore: $livenessScore,
                isDuplicate: $isDuplicate,
                doorStatus: 'not_applicable'
            );

            event(new MemberCheckedIn($attendance, $device));

            return CheckInResult::grantedNoDoor($attendance);
        }

        // ── Branch B: Door Access (with or without attendance) ──
        if ($device->isDoorAccess()) {
            // STEP 8B-1: Check for existing attendance today
            // (prevents duplicate if gym has a separate attendance_only device)
            $existingAttendance = Attendances::where('gym_id', $device->gym_id)
                ->whereHasMorph('attendanceable', [GymMember::class],
                    fn ($q) => $q->where('id', $gymMember->id)
                )
                ->whereDate('check_in_time', today())
                ->where('check_in_result', 'granted')
                ->whereNull('check_out_time')
                ->with('attendanceable')
                ->first();

            // STEP 8B-2: Attempt door unlock
            $doorResult = $this->doorControlService->unlock($device);

            // STEP 8B-3: If door unlock FAILED via API → do not create attendance
            // Relay/Wiegand return skipped() which counts as success
            if ($doorResult->status === 'failed') {
                // Log the door failure
                $this->doorControlService->logEvent(
                    device: $device,
                    gymMember: $gymMember,
                    attendance: null,
                    eventType: 'unlock_failed',
                    result: $doorResult
                );

                // Return error — let device show failure message
                return new CheckInResult(
                    granted: false,
                    result: 'door_failed',
                    denialReason: 'door_controller_unreachable',
                    attendance: null,
                    doorResult: $doorResult
                );
            }

            // STEP 8B-4: Door succeeded
            // Create attendance only if:
            // (a) device purpose is door_access (not door_access_no_attendance)
            // (b) no attendance already exists today from another device
            $attendance = null;

            if ($device->shouldCreateAttendance() && ! $existingAttendance) {
                $attendance = $this->createAttendance(
                    gymMember: $gymMember,
                    device: $device,
                    verificationResult: $verificationResult,
                    livenessScore: $livenessScore,
                    isDuplicate: $isDuplicate,
                    doorStatus: $doorResult->status,
                    doorUnlockedAt: $doorResult->unlockedAt
                );
            }

            // If attendance already existed, update its door status
            if ($existingAttendance && $device->shouldCreateAttendance()) {
                $existingAttendance->update([
                    'door_status' => $doorResult->status,
                    'door_unlocked_at' => $doorResult->unlockedAt,
                ]);
                $attendance = $existingAttendance;
            }

            // Log door event
            $this->doorControlService->logEvent(
                device: $device,
                gymMember: $gymMember,
                attendance: $attendance,
                eventType: 'unlock_granted',
                result: $doorResult
            );

            // Only fire check-in event if there's an attendance record
            if ($device->shouldCreateAttendance() && ($attendance || $existingAttendance)) {
                event(new MemberCheckedIn($attendance ?? $existingAttendance, $device));
            }

            return CheckInResult::granted($attendance ?? $existingAttendance, $doorResult);
        }

        // Fallback — should never reach here
        return CheckInResult::denied('unknown_device_purpose');
    }

    /**
     * Create attendance record with biometric and door information
     */
    private function createAttendance(
        GymMember $gymMember,
        BiometricDevice $device,
        VerificationResult $verificationResult,
        float $livenessScore,
        bool $isDuplicate,
        string $doorStatus,
        ?Carbon $doorUnlockedAt = null
    ): Attendances {
        /** `@var` Attendances */
        $attendance = $gymMember->attendances()->create([
            'gym_id' => $device->gym_id,
            'check_in_time' => now(),
            'status' => AttendanceStatus::Present,
            'check_in_method' => 'biometric',
            'device_id' => $device->id,
            'biometric_type' => $device->device_type,
            'provider_type' => $device->provider_type,
            'confidence_score' => $verificationResult->confidence ?? null,
            'liveness_score' => $livenessScore,
            'check_in_result' => $isDuplicate ? 'suspicious' : 'granted',
            'door_status' => $doorStatus,
            'door_unlocked_at' => $doorUnlockedAt,
            'check_in_ip' => request()->ip(),
        ]);

        // Eager-load the polymorphic relation for webhook response
        $attendance->load('attendanceable');

        return $attendance;
    }

    /**
     * Deny access and log the denial
     */
    private function denyAndLog(
        BiometricDevice $device,
        ?GymMember $gymMember,
        string $reason
    ): CheckInResult {
        // Log door denied event (always log door events regardless of attendance creation)
        if ($device->isDoorAccess()) {
            // Create minimal attendance for door event logging if needed
            $attendanceForLog = null;
            if ($gymMember && $device->shouldCreateAttendance()) {
                $attendanceForLog = $gymMember->attendances()->create([
                    'gym_id' => $device->gym_id,
                    'check_in_time' => now(),
                    'check_out_time' => now(), // Immediately checkout so it's not counted as active
                    'status' => AttendanceStatus::Absent, // Denied = not present
                    'check_in_method' => 'biometric',
                    'device_id' => $device->id,
                    'check_in_result' => 'denied',
                    'denial_reason' => $reason,
                    'door_status' => 'locked',
                    'door_denial_reason' => $reason,
                ]);
            }

            $this->doorControlService->logEvent(
                device: $device,
                gymMember: $gymMember,
                attendance: $attendanceForLog,
                eventType: 'unlock_denied',
                result: DoorControlResult::locked($reason)
            );
        } elseif ($gymMember && $device->shouldCreateAttendance()) {
            // Attendance-only device: create audit record for denied attempt
            $gymMember->attendances()->create([
                'gym_id' => $device->gym_id,
                'check_in_time' => now(),
                'check_out_time' => now(), // Immediately checkout so it's not counted as active
                'status' => AttendanceStatus::Absent, // Denied = not present
                'check_in_method' => 'biometric',
                'device_id' => $device->id,
                'check_in_result' => 'denied',
                'denial_reason' => $reason,
            ]);
        }

        return CheckInResult::denied($reason);
    }

    public function processCheckOut(GymMember $gymMember, BiometricDevice $device): CheckInResult
    {
        // Find active attendance
        $attendance = Attendances::where('attendanceable_id', $gymMember->id)
            ->where('attendanceable_type', GymMember::class)
            ->where('gym_id', $device->gym_id)
            ->active()
            ->with('attendanceable')
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
