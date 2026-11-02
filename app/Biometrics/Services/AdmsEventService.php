<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\DTOs\AdmsEventResult;
use App\Biometrics\DTOs\DoorControlResult;
use App\Enums\AttendanceStatus;
use App\Enums\BiometricTypeEnum;
use App\Events\AdmsAccessDenied;
use App\Events\MemberCheckedIn;
use App\Events\MemberCheckedOut;
use App\Models\Attendances;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdmsEventService
{
    public function __construct(
        protected MembershipGuard $membershipGuard,
        protected RedisBiometricCacheService $biometricCache,
        protected FraudDetector $fraudDetector,
        protected DoorControlService $doorControlService,
    ) {}

    /**
     * Process a single ADMS attendance push event.
     *
     * @param  int  $status  ATTLOG status: 0=check-in, 1=check-out, 2=break-out, 3=break-in, 4=OT-in, 5=OT-out
     * @param  int  $verifyMode  0=password, 1=fingerprint, 2=card, 9=palm, 15=face
     */
    public function processEvent(
        BiometricDevice $device,
        string $userId,
        Carbon $timestamp,
        int $status,
        int $verifyMode = 0
    ): AdmsEventResult {
        // ── Step 1: Resolve member ──────────────────────
        $gymMember = $this->resolveMember($device, $userId);

        if (! $gymMember) {
            Log::warning('ADMS: Unknown user PIN', [
                'device_id' => $device->id,
                'user_id_hash' => hash('sha256', $userId),
                'gym_id' => $device->gym_id,
            ]);

            return AdmsEventResult::denied('unknown_member');
        }

        // ── Step 2: Determine check-in vs check-out ────
        $isCheckOut = $this->shouldCheckOut($status, $gymMember, $device, $timestamp);

        if ($isCheckOut) {
            return $this->processCheckOut($gymMember, $device, $timestamp);
        }

        // Status codes 2-5 are break-out, break-in, OT-in, OT-out.
        // If not a normal punch (0 or 1), acknowledge without creating open attendance records.
        if (! in_array($status, [0, 1], true)) {
            Log::info('ADMS: Non-attendance punch status ignored', BiometricLogRedactor::redact([
                'device_id' => $device->id,
                'user_id' => $userId,
                'status' => $status,
            ]));

            return AdmsEventResult::ignored('status_' . $status);
        }

        // ── Step 3: Membership guard (for check-in) ──────
        $guardResult = $this->membershipGuard->canCheckIn($gymMember);

        if (! $guardResult->allowed) {
            $reason = $guardResult->reason ?? 'access_denied';
            $this->broadcastDenied($device, $gymMember, $reason);

            return AdmsEventResult::denied($reason);
        }

        // ── Step 4: Anti-passback lock (only for check-in) ──
        $lockToken = null;
        $lockAcquired = $this->biometricCache->acquireAntiPassbackLock(
            $device->gym_id,
            $gymMember->id,
            null,
            $lockToken
        );

        if (! $lockAcquired) {
            $this->broadcastDenied($device, $gymMember, 'anti_passback');

            Log::info('ADMS: Anti-passback denied check-in', BiometricLogRedactor::redact([
                'device_id' => $device->id,
                'member_id' => $gymMember->id,
                'gym_id' => $device->gym_id,
            ]));

            return AdmsEventResult::denied('anti_passback');
        }

        // ── Step 5: Fraud velocity inspection ──────────────
        if ($this->fraudDetector->isSuspiciousVelocity($gymMember)) {
            Log::warning('ADMS: Suspicious scan velocity detected', BiometricLogRedactor::redact([
                'member_id' => $gymMember->id,
                'device_id' => $device->id,
            ]));
        }

        try {
            $result = $this->processCheckIn($gymMember, $device, $timestamp, $verifyMode);

            // Door-only devices that do not record attendance should not hold standing cooldown lock
            if (! $device->shouldCreateAttendance() && $lockToken !== null) {
                $this->biometricCache->releaseAntiPassbackLock($device->gym_id, $gymMember->id, $lockToken);
            }

            return $result;
        } catch (Throwable $e) {
            // Transient error in check-in processing — release anti-passback lock with fencing token so subsequent retries are not locked out
            if ($lockToken !== null) {
                $this->biometricCache->releaseAntiPassbackLock($device->gym_id, $gymMember->id, $lockToken);
            }

            throw $e;
        }
    }

    /**
     * Resolve GymMember from the ADMS user PIN / hardware identifier.
     * Checks device-scoped template_ref first, then falls back to strict positive integer GymMember ID.
     */
    private function resolveMember(BiometricDevice $device, string $userId): ?GymMember
    {
        $member = null;

        // 1. Check device-scoped template_ref first. A null device is only a
        // cloud-backed row: a hardware template orphaned by device deletion may
        // still be held by a terminal this punch did not come from, so matching it
        // here would attribute attendance to a member who never enrolled on this
        // device.
        $template = BiometricTemplate::query()
            ->where(function (Builder $q) use ($device): void {
                $q->where('enrolled_by_device_id', $device->id)
                    ->orWhere(function (Builder $unbound): void {
                        $unbound->whereNull('enrolled_by_device_id')->cloudBacked();
                    });
            })
            ->where('template_ref', $userId)
            ->where('gym_id', $device->gym_id)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $template = BiometricTemplate::findByTemplateRef($device->gym_id, $userId, $device->id);
        }

        if ($template?->gymMember && $template->gymMember->gym_id === $device->gym_id) {
            $member = $template->gymMember;
        }

        // 2. Fall back to direct member ID only if strictly positive integer
        if (! $member && ctype_digit($userId) && (int) $userId > 0) {
            $member = GymMember::query()
                ->where('gym_id', $device->gym_id)
                ->where('id', (int) $userId)
                ->first();
        }

        if (! $member) {
            return null;
        }

        // Verify member has active biometric consent
        if (! $member->hasActiveBiometricConsent()) {
            Log::info('ADMS: Member lacks active biometric consent', BiometricLogRedactor::redact([
                'gym_id' => $device->gym_id,
                'member_id' => $member->id,
                'user_id' => $userId,
                'user_id_hash' => hash('sha256', $userId),
            ]));

            return null;
        }

        return $member;
    }

    /**
     * Determine whether the event should be treated as a check-out.
     *
     * Status 1 = always check-out.
     * Status 0 = check-in, but if the member already has an active (open) attendance on the event date, auto-detect as check-out.
     */
    private function shouldCheckOut(int $status, GymMember $gymMember, BiometricDevice $device, Carbon $timestamp): bool
    {
        // Explicit check-out from device status code
        if ($status === 1) {
            return true;
        }

        // Auto-detect: if member has an open attendance on the event date, treat as check-out
        if ($status === 0) {
            return Attendances::query()
                ->where('gym_id', $device->gym_id)
                ->where('attendanceable_id', $gymMember->id)
                ->where('attendanceable_type', GymMember::class)
                ->whereDate('check_in_time', $timestamp->toDateString())
                ->where('check_in_result', 'granted')
                ->whereNull('check_out_time')
                ->exists();
        }

        return false;
    }

    private function resolveBiometricType(int $verifyMode, BiometricDevice $device): BiometricTypeEnum|string
    {
        return match ($verifyMode) {
            1 => BiometricTypeEnum::Fingerprint,
            2 => BiometricTypeEnum::Card,
            15 => BiometricTypeEnum::Face,
            default => $device->device_type,
        };
    }

    private function processCheckIn(
        GymMember $gymMember,
        BiometricDevice $device,
        Carbon $timestamp,
        int $verifyMode = 0
    ): AdmsEventResult {
        $attendance = null;

        if ($device->shouldCreateAttendance()) {
            /** @var Attendances $attendance */
            $attendance = $gymMember->attendances()->create([
                'gym_id' => $device->gym_id,
                'check_in_time' => $timestamp,
                'status' => AttendanceStatus::Present,
                'check_in_method' => 'biometric',
                'device_id' => $device->id,
                'biometric_type' => $this->resolveBiometricType($verifyMode, $device),
                'provider_type' => 'adms',
                'confidence_score' => 1.0,
                'liveness_score' => 1.0,
                'check_in_result' => 'granted',
                'door_status' => $device->isDoorAccess() ? 'skipped' : 'not_applicable',
                'check_in_ip' => request()->ip(),
            ]);

            $attendance->load('attendanceable');

            event(new MemberCheckedIn($attendance, $device));
        }

        // Log door event if applicable
        if ($device->isDoorAccess()) {
            $this->doorControlService->logEvent(
                device: $device,
                gymMember: $gymMember,
                attendance: $attendance,
                eventType: 'unlock_granted',
                result: DoorControlResult::skipped()
            );
        }

        return $attendance
            ? AdmsEventResult::checkedIn($attendance)
            : AdmsEventResult::ignored('access_granted_no_attendance');
    }

    private function processCheckOut(
        GymMember $gymMember,
        BiometricDevice $device,
        Carbon $timestamp
    ): AdmsEventResult {
        return DB::transaction(function () use ($gymMember, $device, $timestamp) {
            // Find active attendance matching member, gym, and check_in_time <= timestamp
            /** @var Attendances|null $attendance */
            $attendance = Attendances::query()
                ->where('attendanceable_id', $gymMember->id)
                ->where('attendanceable_type', GymMember::class)
                ->where('gym_id', $device->gym_id)
                ->whereNull('check_out_time')
                ->where('check_in_time', '<=', $timestamp)
                ->latest('check_in_time')
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                return AdmsEventResult::denied('no_active_check_in');
            }

            $attendance->update([
                'check_out_time' => $timestamp,
                'status' => AttendanceStatus::Completed,
            ]);

            broadcast(new MemberCheckedOut($attendance, $device));

            return AdmsEventResult::checkedOut($attendance);
        });
    }

    private function broadcastDenied(BiometricDevice $device, GymMember $gymMember, string $reason): void
    {
        broadcast(new AdmsAccessDenied(
            gymId: $device->gym_id,
            memberId: $gymMember->id,
            memberName: $gymMember->name,
            deviceName: $device->device_name,
            denialReason: $reason,
            timestamp: now()->toIso8601String(),
        ));
    }
}
