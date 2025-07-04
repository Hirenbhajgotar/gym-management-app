<?php
// app/Services/AvatarService.php
namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendances;
use App\Models\GymMember;
use App\Models\Trainer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    public $attendanceModels = [];

    public function __construct()
    {
        self::$attendanceModels = [
            GymMember::class,
            Trainer::class,
            User::class,
        ];
    }
    /**
     * Check in a user (gym member or trainer)
     */
    public static function checkIn(int $userId, string $userType, int $gymId, ?string $notes = null): Attendances
    {
        // Validate user type
        if (!in_array($userType, self::$attendanceModels)) {
            throw new \Exception('Invalid user type.');
        }

        // Check if user is already checked in today
        $existingAttendance = Attendances::where('attendanceable_id', $userId)
            ->where('attendanceable_type', $userType)
            ->where('gym_id', $gymId)
            ->whereDate('check_in_time', now()->toDateString())
            ->first();

        if ($existingAttendance && $existingAttendance->check_in_time) {
            throw new \Exception('User is already checked in today.');
        }

        // Create new attendance record
        return Attendances::create([
            'attendanceable_id' => $userId,
            'attendanceable_type' => $userType,
            'gym_id' => $gymId,
            'check_in_time' => now(),
            'status' => AttendanceStatus::Present,
            'notes' => $notes,
        ]);
    }

    /**
     * Check out a user (gym member or trainer)
     */
    public static function checkOut(int $userId, string $userType, int $gymId, ?string $notes = null): Attendances
    {
        // Validate user type
        if (!in_array($userType, self::$attendanceModels)) {
            throw new \Exception('Invalid user type.');
        }

        $attendance = Attendances::where('attendanceable_id', $userId)
            ->where('attendanceable_type', $userType)
            ->where('gym_id', $gymId)
            ->active()
            ->first();

        if (!$attendance) {
            throw new \Exception('No active check-in found for this user.');
        }

        $attendance->update([
            'check_out_time' => now(),
            'status' => AttendanceStatus::Completed,
            'notes' => $notes ? ($attendance->notes ? $attendance->notes . "\n" . $notes : $notes) : $attendance->notes,
        ]);

        return $attendance;
    }

    /**
     * Mark a user as absent
     */
    public static function markAbsent(int $userId, string $userType, int $gymId, ?string $notes = null): Attendances
    {
        // Validate user type
        if (!in_array($userType, [GymMember::class, Trainer::class])) {
            throw new \Exception('Invalid user type.');
        }

        return Attendances::create([
            'attendanceable_id' => $userId,
            'attendanceable_type' => $userType,
            'gym_id' => $gymId,
            'status' => AttendanceStatus::Absent,
            'notes' => $notes,
        ]);
    }

    /**
     * Get attendance statistics for a date range
     */
    public static function getAttendanceStats(int $gymId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $query = Attendances::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_records' => $query->count(),
            'present_count' => $query->where('status', AttendanceStatus::Present)->count(),
            'absent_count' => $query->where('status', AttendanceStatus::Absent)->count(),
            'completed_count' => $query->where('status', AttendanceStatus::Completed)->count(),
            'average_duration' => $query->completed()->avg('duration_in_minutes'),
        ];
    }

    /**
     * Get currently checked in members
     */
    public static function getCurrentlyCheckedIn(int $gymId): Collection
    {
        return Attendances::with('attendanceable')
            ->where('gym_id', $gymId)
            ->active()
            ->get();
    }

    /**
     * Auto check-out members who forgot to check out
     */
    public static function autoCheckOut(int $gymId, int $hoursThreshold = 12): int
    {
        $threshold = now()->subHours($hoursThreshold);

        $attendances = Attendances::where('gym_id', $gymId)
            ->active()
            ->where('check_in_time', '<', $threshold)
            ->get();

        foreach ($attendances as $attendance) {
            $attendance->update([
                'check_out_time' => $attendance->check_in_time->addHours($hoursThreshold),
                'status' => AttendanceStatus::Completed,
                'notes' => ($attendance->notes ? $attendance->notes . "\n" : '') . 'Auto checked out after ' . $hoursThreshold . ' hours',
            ]);
        }

        return $attendances->count();
    }

    /**
     * Generate attendance report for a user (member or trainer)
     */
    public static function generateUserReport(int $userId, string $userType, int $gymId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        // Validate user type
        if (!in_array($userType, self::$attendanceModels)) {
            throw new \Exception('Invalid user type.');
        }

        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $attendances = Attendances::where('attendanceable_id', $userId)
            ->where('attendanceable_type', $userType)
            ->where('gym_id', $gymId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $presentDays = $attendances->where('status', AttendanceStatus::Present)->count();
        $completedDays = $attendances->where('status', AttendanceStatus::Completed)->count();
        $absentDays = $attendances->where('status', AttendanceStatus::Absent)->count();

        return [
            'user_type' => $userType === GymMember::class ? 'Member' : 'Trainer',
            'total_days' => $totalDays,
            'present_days' => $presentDays,
            'completed_days' => $completedDays,
            'absent_days' => $absentDays,
            'attendance_rate' => $totalDays > 0 ? (($presentDays + $completedDays) / $totalDays) * 100 : 0,
            'total_workout_time' => $attendances->sum('duration_in_minutes'),
            'average_workout_time' => $attendances->where('status', AttendanceStatus::Completed)->avg('duration_in_minutes'),
            'attendances' => $attendances,
        ];
    }
}
