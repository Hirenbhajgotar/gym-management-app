<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendances;
use App\Models\GymMember;
use Carbon\Carbon;

class QRAttendanceService
{
    /**
     * Check in a member using QR code attendance.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string, attendance?: Attendances}
     */
    public function checkIn(array $data): array
    {
        $gymId = $data['gym_id'];
        $memberId = $data['member_id'];
        $identifier = $data['identifier'] ?? 'member_id'; // 'member_id' or 'phone'

        // Find the member
        $member = $this->findMember($gymId, $memberId, $identifier);

        if (! $member) {
            return [
                'success' => false,
                'message' => 'Member not found. Please check your Member ID or phone number and try again.',
            ];
        }

        // Check if member can access gym
        if (! $member->canAccessGym()) {
            $reason = $this->getAccessDeniedReason($member);

            return [
                'success' => false,
                'message' => $reason,
            ];
        }

        // Check for existing attendance today (duplicate prevention)
        $existingAttendance = $this->getTodayAttendance($member);

        if ($existingAttendance) {
            // Already checked in today - return existing attendance
            return [
                'success' => true,
                'message' => 'You are already checked in today.',
                'attendance' => $existingAttendance,
                'already_checked_in' => true,
            ];
        }

        // Create new attendance
        $attendance = $this->createAttendance($member, $gymId);

        return [
            'success' => true,
            'message' => 'Check-in successful! Welcome to the gym.',
            'attendance' => $attendance,
        ];
    }

    /**
     * Find member by member ID or phone number.
     */
    public function findMember(int $gymId, string $value, string $identifier): ?GymMember
    {
        $query = GymMember::where('gym_id', $gymId);

        if ($identifier === 'phone') {
            return $query->where('phone', $value)->first();
        }

        // Default: search by member_id (custom_id or id)
        return $query->where('id', $value)->first();
    }

    /**
     * Get today's attendance for the member.
     */
    public function getTodayAttendance(GymMember $member): ?Attendances
    {
        return Attendances::where('attendanceable_id', $member->id)
            ->where('attendanceable_type', GymMember::class)
            ->where('gym_id', $member->gym_id)
            ->whereDate('check_in_time', Carbon::today())
            ->first();
    }

    /**
     * Create new attendance record.
     */
    protected function createAttendance(GymMember $member, int $gymId): Attendances
    {
        return Attendances::create([
            'attendanceable_id' => $member->id,
            'attendanceable_type' => GymMember::class,
            'gym_id' => $gymId,
            'check_in_time' => Carbon::now(),
            'status' => AttendanceStatus::Present,
            'check_in_method' => 'qr_code',
        ]);
    }

    /**
     * Get reason why member cannot access gym.
     */
    public function getAccessDeniedReason(GymMember $member): string
    {
        if ($member->isFrozen()) {
            $reason = 'Your membership is currently frozen.';
            if ($member->frozen_until) {
                $reason .= ' It will be unfrozen on ' . $member->frozen_until->format('F j, Y') . '.';
            }

            return $reason;
        }

        return 'Your membership has expired. Please renew to check in.';
    }
}
