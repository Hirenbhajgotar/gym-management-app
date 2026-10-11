<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\CheckInResult;
use App\Biometrics\DTOs\DoorControlResult;
use App\Biometrics\DTOs\TemplateRef;
use App\Enums\AttendanceStatus;
use App\Events\MemberCheckedIn;
use App\Events\MemberCheckedOut;
use App\Events\SuspiciousCheckInDetected;
use App\Models\Attendances;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class CheckInService
{
    public function __construct(
        protected BiometricProviderRegistry $registry,
        protected AntiSpoofGuard $antiSpoofGuard,
        protected MembershipGuard $membershipGuard,
        protected FraudDetector $fraudDetector,
        protected FusionEngine $fusionEngine,
        protected DoorControlService $doorControlService,
        protected RedisBiometricCacheService $biometricCache
    ) {}

    public function processCheckIn(BiometricSample $sample, BiometricDevice $device): CheckInResult
    {
        // ─────────────────────────────────────────────
        // STEP 1: Resolve provider
        // ─────────────────────────────────────────────
        if (! $device->provider_type) {
            return $this->denyAndLog($device, null, 'provider_not_configured');
        }

        $provider = $this->registry->resolve($device->provider_type, $device->provider_config ?? []);

        // ─────────────────────────────────────────────
        // STEP 2: Liveness check
        // ─────────────────────────────────────────────
        $livenessScore = $this->antiSpoofGuard->getLivenessScore($sample, $provider);
        $livenessThreshold = config()->float('biometrics.liveness_threshold', 0.75);
        if ($livenessScore < $livenessThreshold) {
            return $this->denyAndLog($device, null, 'liveness_failed');
        }

        // ─────────────────────────────────────────────
        // STEP 3: Load biometric template
        // ─────────────────────────────────────────────
        $templates = BiometricTemplate::query()->where('gym_id', $device->gym_id)
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
            return $this->denyAndLog($device, $gymMember, $guardResult->reason ?? 'access_denied');
        }

        // ─────────────────────────────────────────────
        // STEP 7: Fraud detection & Anti-Passback
        // ─────────────────────────────────────────────
        $fraudWindowSeconds = config()->integer('biometrics.anti_passback_window_seconds', 60);
        $lockToken = null;
        $antiPassbackAcquired = $this->biometricCache->acquireAntiPassbackLock(
            $device->gym_id,
            $gymMember->id,
            $fraudWindowSeconds,
            $lockToken
        );

        if (! $antiPassbackAcquired) {
            return $this->denyAndLog($device, $gymMember, 'anti_passback_rejection');
        }

        $isDuplicate = $this->fraudDetector->isDuplicate($gymMember);

        if ($this->fraudDetector->isSuspiciousVelocity($gymMember)) {
            broadcast(new SuspiciousCheckInDetected($gymMember, $device, 'suspicious_velocity'));
        }

        // ─────────────────────────────────────────────
        // STEP 8: Finalize Check-In (Door + Attendance)
        // ─────────────────────────────────────────────
        try {
            $result = $this->finalizeCheckIn(
                gymMember: $gymMember,
                device: $device,
                confidenceScore: $verificationResult->confidence ?? null,
                livenessScore: $livenessScore,
                isDuplicate: $isDuplicate,
                providerType: $device->provider_type->value
            );

            if (! $result->granted && $lockToken !== null) {
                $this->biometricCache->releaseAntiPassbackLock($device->gym_id, $gymMember->id, $lockToken);
            }

            return $result;
        } catch (Throwable $e) {
            if ($lockToken !== null) {
                $this->biometricCache->releaseAntiPassbackLock($device->gym_id, $gymMember->id, $lockToken);
            }

            throw $e;
        }
    }

    /**
     * Process check-in for devices that verify biometric templates on-device (Device-Native Mode)
     *
     * @param  array<string, mixed>  $metadata
     */
    public function processDeviceNativeCheckIn(
        BiometricDevice $device,
        mixed $memberIdentifier,
        array $metadata = []
    ): CheckInResult {
        if (blank($memberIdentifier)) {
            return $this->denyAndLog($device, null, 'member_identifier_missing');
        }

        $idStr = is_string($memberIdentifier) || is_numeric($memberIdentifier) ? (string) $memberIdentifier : '';
        if ($idStr === '') {
            return $this->denyAndLog($device, null, 'member_identifier_missing');
        }

        $gymMember = null;

        // 1. PIN is member ID: verify if numeric and belongs to device gym
        if (ctype_digit($idStr) || is_numeric($idStr)) {
            $gymMember = GymMember::query()
                ->where('gym_id', $device->gym_id)
                ->where('id', (int) $idStr)
                ->first();
        }

        // 2. If not found by direct ID, check template_ref
        if (! $gymMember) {
            $template = BiometricTemplate::findByTemplateRef($device->gym_id, $idStr);
            if ($template?->gymMember && $template->gymMember->gym_id === $device->gym_id) {
                $gymMember = $template->gymMember;
            }
        }

        if (! $gymMember) {
            return $this->denyAndLog($device, null, 'member_not_found');
        }

        // 3. Fast-path: Check Redis member access cache for the verified member
        $summary = $this->biometricCache->getMemberAccessSummary($device->gym_id, $gymMember->id);

        // 2. Validate Membership Access via cached summary / guard
        if ($summary && ! $summary['can_access']) {
            return $this->denyAndLog($device, $gymMember, $summary['denial_reason'] ?? 'access_denied');
        }

        $guardResult = $this->membershipGuard->canCheckIn($gymMember);
        if (! $guardResult->allowed) {
            return $this->denyAndLog($device, $gymMember, $guardResult->reason ?? 'access_denied');
        }

        // 3. Atomic Anti-Passback Lock via Redis (O(1) memory check, prevents DB lock contention)
        $fraudWindowSeconds = config()->integer('biometrics.anti_passback_window_seconds', 60);
        $lockToken = null;
        $antiPassbackAcquired = $this->biometricCache->acquireAntiPassbackLock(
            $device->gym_id,
            $gymMember->id,
            $fraudWindowSeconds,
            $lockToken
        );

        if (! $antiPassbackAcquired) {
            return $this->denyAndLog($device, $gymMember, 'anti_passback_rejection');
        }

        $isDuplicate = $this->fraudDetector->isDuplicate($gymMember);

        if ($this->fraudDetector->isSuspiciousVelocity($gymMember)) {
            broadcast(new SuspiciousCheckInDetected($gymMember, $device, 'suspicious_velocity'));
        }

        try {
            $result = $this->finalizeCheckIn(
                gymMember: $gymMember,
                device: $device,
                confidenceScore: 1.0,
                livenessScore: 1.0,
                isDuplicate: $isDuplicate,
                providerType: 'device_native'
            );

            if (! $result->granted && $lockToken !== null) {
                $this->biometricCache->releaseAntiPassbackLock($device->gym_id, $gymMember->id, $lockToken);
            }

            return $result;
        } catch (Throwable $e) {
            if ($lockToken !== null) {
                $this->biometricCache->releaseAntiPassbackLock($device->gym_id, $gymMember->id, $lockToken);
            }

            throw $e;
        }
    }

    /**
     * Finalize check-in by handling door control, creating attendance, and firing events
     */
    private function finalizeCheckIn(
        GymMember $gymMember,
        BiometricDevice $device,
        ?float $confidenceScore,
        float $livenessScore,
        bool $isDuplicate,
        ?string $providerType = null
    ): CheckInResult {
        // ── Branch A: Attendance Only ──────────────────
        if ($device->isAttendanceOnly()) {
            $attendance = $this->createAttendance(
                gymMember: $gymMember,
                device: $device,
                confidenceScore: $confidenceScore,
                livenessScore: $livenessScore,
                isDuplicate: $isDuplicate,
                doorStatus: 'not_applicable',
                providerType: $providerType
            );

            event(new MemberCheckedIn($attendance, $device));

            return CheckInResult::grantedNoDoor($attendance);
        }

        // ── Branch B: Door Access (with or without attendance) ──
        if ($device->isDoorAccess()) {
            // Check for existing attendance today (prevents duplicates for multi-device gyms)
            $existingAttendance = Attendances::query()->where('gym_id', $device->gym_id)
                ->whereHasMorph('attendanceable', [GymMember::class],
                    fn (Builder $q) => $q->where('id', $gymMember->id)
                )
                ->whereDate('check_in_time', today())
                ->where('check_in_result', 'granted')
                ->whereNull('check_out_time')
                ->with('attendanceable')
                ->first();

            // Attempt door unlock
            $doorResult = $this->doorControlService->unlock($device);

            // If door unlock FAILED via API → do not create attendance
            if ($doorResult->status === 'failed') {
                $this->doorControlService->logEvent(
                    device: $device,
                    gymMember: $gymMember,
                    attendance: null,
                    eventType: 'unlock_failed',
                    result: $doorResult
                );

                return new CheckInResult(
                    granted: false,
                    result: 'door_failed',
                    denialReason: 'door_controller_unreachable',
                    attendance: null,
                    doorResult: $doorResult
                );
            }

            // Door succeeded: Create or update attendance
            $attendance = null;
            $confirmedUnlockAt = $doorResult->status === 'unlocked' ? $doorResult->unlockedAt : null;

            if ($device->shouldCreateAttendance() && ! $existingAttendance) {
                $attendance = $this->createAttendance(
                    gymMember: $gymMember,
                    device: $device,
                    confidenceScore: $confidenceScore,
                    livenessScore: $livenessScore,
                    isDuplicate: $isDuplicate,
                    doorStatus: $doorResult->status,
                    doorUnlockedAt: $confirmedUnlockAt,
                    providerType: $providerType
                );
            }

            if ($existingAttendance && $device->shouldCreateAttendance()) {
                $existingAttendance->update([
                    'door_status' => $doorResult->status,
                    'door_unlocked_at' => $confirmedUnlockAt,
                ]);
                $attendance = $existingAttendance;
            }

            // Log door event
            $this->doorControlService->logEvent(
                device: $device,
                gymMember: $gymMember,
                attendance: $attendance,
                eventType: $doorResult->status === 'unlocked' ? 'unlock_granted' : 'directive_sent',
                result: $doorResult
            );

            if ($device->shouldCreateAttendance() && ($attendance || $existingAttendance)) {
                event(new MemberCheckedIn($attendance ?? $existingAttendance, $device));
            }

            if (! $device->shouldCreateAttendance()) {
                return CheckInResult::grantedWithoutAttendance($doorResult);
            }

            return CheckInResult::granted($attendance ?? $existingAttendance, $doorResult);
        }

        return CheckInResult::denied('unknown_device_purpose');
    }

    /**
     * Create attendance record with biometric and door information
     */
    private function createAttendance(
        GymMember $gymMember,
        BiometricDevice $device,
        ?float $confidenceScore,
        float $livenessScore,
        bool $isDuplicate,
        string $doorStatus,
        ?Carbon $doorUnlockedAt = null,
        ?string $providerType = null
    ): Attendances {
        /** @var Attendances $attendance */
        $attendance = $gymMember->attendances()->create([
            'gym_id' => $device->gym_id,
            'check_in_time' => now(),
            'status' => AttendanceStatus::Present,
            'check_in_method' => 'biometric',
            'device_id' => $device->id,
            'biometric_type' => $device->device_type,
            'provider_type' => $providerType ?? $device->provider_type?->value,
            'confidence_score' => $confidenceScore,
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
        $attendance = Attendances::query()->where('attendanceable_id', $gymMember->id)
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
