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
    public static $attendanceModels = [];

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

        // Validate that the user exists and belongs to the gym
        self::validateUserExistsInGym($userId, $userType, $gymId);

        // Check if user is already checked in today
        $existingAttendance = Attendances::where('attendanceable_id', $userId)
            ->where('attendanceable_type', $userType)
            ->where('gym_id', $gymId)
            ->whereDate('check_in_time', now()->toDateString())
            ->whereNull('check_out_time')
            ->first();

        if ($existingAttendance) {
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

        // Calculate duration
        $duration = $attendance->check_in_time->diffInMinutes(now());

        $attendance->update([
            'check_out_time' => now(),
            'status' => AttendanceStatus::Completed,
            'duration_in_minutes' => $duration,
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

        // Validate that the user exists and belongs to the gym
        self::validateUserExistsInGym($userId, $userType, $gymId);

        return Attendances::create([
            'attendanceable_id' => $userId,
            'attendanceable_type' => $userType,
            'gym_id' => $gymId,
            'status' => AttendanceStatus::Absent,
            'notes' => $notes,
            'created_at' => now(),
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

        $totalRecords = $query->count();
        $presentCount = $query->where('status', AttendanceStatus::Present)->count();
        $absentCount = $query->where('status', AttendanceStatus::Absent)->count();
        $completedCount = $query->where('status', AttendanceStatus::Completed)->count();

        // Get member and trainer specific stats
        $memberStats = self::getUserTypeStats($gymId, GymMember::class, $startDate, $endDate);
        $trainerStats = self::getUserTypeStats($gymId, Trainer::class, $startDate, $endDate);

        return [
            'total_records' => $totalRecords,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'completed_count' => $completedCount,
            'average_duration' => $query->completed()->avg('duration_in_minutes'),
            'member_stats' => $memberStats,
            'trainer_stats' => $trainerStats,
        ];
    }

    /**
     * Get stats for specific user type (members or trainers)
     */
    private static function getUserTypeStats(int $gymId, string $userType, Carbon $startDate, Carbon $endDate): array
    {
        $query = Attendances::where('gym_id', $gymId)
            ->where('attendanceable_type', $userType)
            ->whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_records' => $query->count(),
            'present_count' => $query->where('status', AttendanceStatus::Present)->count(),
            'absent_count' => $query->where('status', AttendanceStatus::Absent)->count(),
            'completed_count' => $query->where('status', AttendanceStatus::Completed)->count(),
            'average_duration' => $query->completed()->avg('duration_in_minutes'),
        ];
    }

    public static function getUserTypeStatsForToday(int $gymId, string $userType, string $entityModel): array
    {
        $today = Carbon::today();

        $query = Attendances::where('gym_id', $gymId)
            ->where('attendanceable_type', $userType);

        return [
            'active_count' => $query->where('status', AttendanceStatus::Present)
                ->whereDate('check_in_time', $today)
                ->whereNull('check_out_time')->count(),

            'absent_count' => app($entityModel)->where('gym_id', $gymId)
                ->whereNotIn(
                    'id',
                    $query->whereDate('check_in_time', $today)->pluck('attendanceable_id')
                )->count(),

            'completed_count' => $query->where('status', AttendanceStatus::Completed)
                ->whereDate('check_in_time', $today)
                ->whereNotNull('check_out_time')->count(),
        ];
    }

    /**
     * Get currently checked in members and trainers
     */
    public static function getCurrentlyCheckedIn(int $gymId): Collection
    {
        return Attendances::with('attendanceable')
            ->where('gym_id', $gymId)
            ->active()
            ->get();
    }

    /**
     * Get currently checked in members only
     */
    public static function getCurrentlyCheckedInMembers(int $gymId): Collection
    {
        return Attendances::with('attendanceable')
            ->where('gym_id', $gymId)
            ->where('attendanceable_type', GymMember::class)
            ->active()
            ->get();
    }

    /**
     * Get currently checked in trainers only
     */
    public static function getCurrentlyCheckedInTrainers(int $gymId): Collection
    {
        return Attendances::with('attendanceable')
            ->where('gym_id', $gymId)
            ->where('attendanceable_type', Trainer::class)
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
            $checkOutTime = $attendance->check_in_time->addHours($hoursThreshold);
            $duration = $attendance->check_in_time->diffInMinutes($checkOutTime);

            $attendance->update([
                'check_out_time' => $checkOutTime,
                'status' => AttendanceStatus::Completed,
                'notes' => ($attendance->notes ? $attendance->notes . "\n" : '') . 'Auto checked out after ' . $hoursThreshold . ' hours',
            ]);
        }

        return $attendances->count();
    }

    /**
     * Auto check-out all gyms
     */
    public static function autoCheckOutAllGyms(int $hoursThreshold = 12): array
    {
        $results = [];
        $threshold = now()->subHours($hoursThreshold);

        // Get all gyms with active attendances
        $gymIds = Attendances::active()
            ->where('check_in_time', '<', $threshold)
            ->distinct('gym_id')
            ->pluck('gym_id');

        foreach ($gymIds as $gymId) {
            $count = self::autoCheckOut($gymId, $hoursThreshold);
            $results[$gymId] = $count;
        }

        return $results;
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
            ->orderBy('created_at', 'desc')
            ->get();

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $presentDays = $attendances->where('status', AttendanceStatus::Present)->count();
        $completedDays = $attendances->where('status', AttendanceStatus::Completed)->count();
        $absentDays = $attendances->where('status', AttendanceStatus::Absent)->count();

        return [
            'user_type' => $userType === GymMember::class ? 'Member' : ($userType === Trainer::class ? 'Trainer' : 'User'),
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

    /**
     * Get gym member attendance summary
     */
    public static function getGymMemberAttendanceSummary(int $gymId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $members = GymMember::where('gym_id', $gymId)->get();
        $summary = [];

        foreach ($members as $member) {
            $report = self::generateUserReport($member->id, GymMember::class, $gymId, $startDate, $endDate);
            $summary[] = [
                'member_id' => $member->id,
                'member_name' => $member->name,
                'member_email' => $member->email,
                'attendance_rate' => $report['attendance_rate'],
                'total_workout_time' => $report['total_workout_time'],
                'completed_days' => $report['completed_days'],
            ];
        }

        return $summary;
    }

    /**
     * Get trainer attendance summary
     */
    public static function getTrainerAttendanceSummary(int $gymId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $trainers = Trainer::where('gym_id', $gymId)->get();
        $summary = [];

        foreach ($trainers as $trainer) {
            $report = self::generateUserReport($trainer->id, Trainer::class, $gymId, $startDate, $endDate);
            $summary[] = [
                'trainer_id' => $trainer->id,
                'trainer_name' => $trainer->name,
                'trainer_email' => $trainer->email,
                'attendance_rate' => $report['attendance_rate'],
                'total_work_time' => $report['total_workout_time'],
                'completed_days' => $report['completed_days'],
            ];
        }

        return $summary;
    }

    /**
     * Validate that user exists and belongs to the gym
     */
    private static function validateUserExistsInGym(int $userId, string $userType, int $gymId): void
    {
        if ($userType === GymMember::class) {
            $user = GymMember::where('id', $userId)->where('gym_id', $gymId)->first();
        } elseif ($userType === Trainer::class) {
            $user = Trainer::where('id', $userId)->where('gym_id', $gymId)->first();
        } else {
            $user = User::find($userId);
        }

        if (!$user) {
            throw new \Exception('User not found or does not belong to this gym.');
        }
    }

    /**
     * Get members who haven't checked in today
     */
    public static function getMembersNotCheckedInToday(int $gymId): Collection
    {
        $today = now()->toDateString();

        $checkedInMemberIds = Attendances::where('gym_id', $gymId)
            ->where('attendanceable_type', GymMember::class)
            ->whereDate('check_in_time', $today)
            ->pluck('attendanceable_id');

        return GymMember::where('gym_id', $gymId)
            ->whereNotIn('id', $checkedInMemberIds)
            ->get();
    }

    /**
     * Get trainers who haven't checked in today
     */
    public static function getTrainersNotCheckedInToday(int $gymId): Collection
    {
        $today = now()->toDateString();

        $checkedInTrainerIds = Attendances::where('gym_id', $gymId)
            ->where('attendanceable_type', Trainer::class)
            ->whereDate('check_in_time', $today)
            ->pluck('attendanceable_id');

        return Trainer::where('gym_id', $gymId)
            ->whereNotIn('id', $checkedInTrainerIds)
            ->get();
    }
}
