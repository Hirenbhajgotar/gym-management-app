<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLoginLog;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Service for querying user login activity.
 *
 * This service provides methods to retrieve login statistics and history
 * for staff roles (gym_owner, manager, receptionist, trainer).
 */
class LoginActivityService
{
    /**
     * Staff roles that should be tracked.
     */
    protected array $staffRoles = ['gym_owner', 'manager', 'receptionist', 'trainer'];

    /**
     * Get user IDs for given roles using the gym_user pivot table.
     */
    public function getUserIdsByRoles(array $roles): Collection
    {
        return User::whereHas('gyms', function (Builder $query) use ($roles) {
            $query->whereIn('gym_user.role', $roles);
        })->pluck('users.id');
    }

    /**
     * Get login logs for a specific user.
     */
    public function getLoginsForUser(int $userId, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $query = UserLoginLog::where('user_id', $userId);

        if ($from) {
            $query->where('login_at', '>=', $from);
        }

        if ($to) {
            $query->where('login_at', '<=', $to);
        }

        return $query->orderByDesc('login_at')->get();
    }

    /**
     * Get login statistics for a specific user.
     *
     * Returns array with today, week, month, and all-time counts.
     */
    public function getLoginStats(int $userId): array
    {
        $today = today();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        return [
            'today' => UserLoginLog::where('user_id', $userId)
                ->where('login_at', '>=', $today)
                ->count(),
            'week' => UserLoginLog::where('user_id', $userId)
                ->where('login_at', '>=', $weekStart)
                ->count(),
            'month' => UserLoginLog::where('user_id', $userId)
                ->where('login_at', '>=', $monthStart)
                ->count(),
            'all_time' => UserLoginLog::where('user_id', $userId)->count(),
        ];
    }

    /**
     * Get login logs for a specific gym.
     */
    public function getLoginsForGym(int $gymId, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $query = UserLoginLog::where('gym_id', $gymId);

        if ($from) {
            $query->where('login_at', '>=', $from);
        }

        if ($to) {
            $query->where('login_at', '<=', $to);
        }

        return $query->with('user')->orderByDesc('login_at')->get();
    }

    /**
     * Get login logs filtered by role.
     */
    public function getLoginsByRole(string $role, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $userIds = $this->getUserIdsByRoles([$role]);

        if ($userIds->isEmpty()) {
            return collect();
        }

        $query = UserLoginLog::whereIn('user_id', $userIds);

        if ($from) {
            $query->where('login_at', '>=', $from);
        }

        if ($to) {
            $query->where('login_at', '<=', $to);
        }

        return $query->with(['user', 'gym'])->orderByDesc('login_at')->get();
    }

    /**
     * Get recent login logs with optional filters.
     */
    public function getRecentLogins(int $limit = 50, ?array $filters = null): Collection
    {
        $query = UserLoginLog::with(['user', 'gym']);

        if (filled($filters['role'])) {
            $userIds = $this->getUserIdsByRoles([$filters['role']]);
            if ($userIds->isNotEmpty()) {
                $query->whereIn('user_id', $userIds);
            } else {
                return collect();
            }
        }

        if (filled($filters['gym_id'])) {
            $query->where('gym_id', $filters['gym_id']);
        }

        if (filled($filters['from'])) {
            $query->where('login_at', '>=', Date::parse($filters['from']));
        }

        if (filled($filters['to'])) {
            $query->where('login_at', '<=', Date::parse($filters['to']));
        }

        return $query->latest('login_at')->limit($limit)->get();
    }

    /**
     * Get aggregate login statistics across all staff.
     */
    public function getAggregateStats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $staffUserIds = $this->getUserIdsByRoles($this->staffRoles);

        if ($staffUserIds->isEmpty()) {
            return [
                'total_logins' => 0,
                'today_logins' => 0,
                'week_logins' => 0,
                'month_logins' => 0,
                'unique_users' => 0,
                'active_sessions' => 0,
                'by_device' => [],
                'by_role' => array_fill_keys($this->staffRoles, 0),
            ];
        }

        $query = UserLoginLog::whereIn('user_id', $staffUserIds);

        if ($from) {
            $query->where('login_at', '>=', $from);
        }

        if ($to) {
            $query->where('login_at', '<=', $to);
        }

        $today = today();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        return [
            'total_logins' => (clone $query)->count(),
            'today_logins' => (clone $query)->where('login_at', '>=', $today)->count(),
            'week_logins' => (clone $query)->where('login_at', '>=', $weekStart)->count(),
            'month_logins' => (clone $query)->where('login_at', '>=', $monthStart)->count(),
            'unique_users' => (clone $query)->distinct('user_id')->count('user_id'),
            'active_sessions' => $this->getActiveSessionsCount(),
            'by_device' => (clone $query)
                ->selectRaw('device_type, COUNT(*) as count')
                ->groupBy('device_type')
                ->pluck('count', 'device_type')
                ->toArray(),
            'by_role' => $this->getLoginCountsByRole($from, $to),
        ];
    }

    /**
     * Get login counts grouped by role.
     */
    protected function getLoginCountsByRole(?Carbon $from, ?Carbon $to): array
    {
        $query = UserLoginLog::query()
            ->join('gym_user', function ($join) {
                $join->on('user_login_logs.user_id', '=', 'gym_user.user_id')
                    ->on('user_login_logs.gym_id', '=', 'gym_user.gym_id');
            })
            ->whereIn('gym_user.role', $this->staffRoles)
            ->selectRaw('gym_user.role, COUNT(user_login_logs.id) as count')
            ->groupBy('gym_user.role');

        if ($from) {
            $query->where('user_login_logs.login_at', '>=', $from);
        }

        if ($to) {
            $query->where('user_login_logs.login_at', '<=', $to);
        }

        $countsByRole = $query->pluck('count', 'role');

        // Ensure all staff roles are present in the results, even with a count of 0
        $results = [];
        foreach ($this->staffRoles as $role) {
            $results[$role] = $countsByRole[$role] ?? 0;
        }

        return $results;
    }

    /**
     * Get available staff roles.
     */
    public function getStaffRoles(): array
    {
        return $this->staffRoles;
    }

    /**
     * Get currently logged in users (active sessions).
     */
    public function getCurrentlyLoggedInUsers(): Collection
    {
        $staffUserIds = $this->getUserIdsByRoles($this->staffRoles);

        if ($staffUserIds->isEmpty()) {
            return collect();
        }

        return UserLoginLog::active()
            ->whereIn('user_id', $staffUserIds)
            ->with(['user', 'gym'])
            ->orderByDesc('login_at')
            ->get();
    }

    /**
     * Get active session for a specific user.
     */
    public function getActiveSessionForUser(int $userId): ?UserLoginLog
    {
        return UserLoginLog::active()
            ->where('user_id', $userId)
            ->with(['user', 'gym'])
            ->first();
    }

    /**
     * Check if a user is currently logged in.
     */
    public function isUserCurrentlyLoggedIn(int $userId): bool
    {
        return UserLoginLog::active()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Get total active sessions count.
     */
    public function getActiveSessionsCount(): int
    {
        $staffUserIds = $this->getUserIdsByRoles($this->staffRoles);

        if ($staffUserIds->isEmpty()) {
            return 0;
        }

        return UserLoginLog::active()
            ->whereIn('user_id', $staffUserIds)
            ->count();
    }

    /**
     * Mark a user's current session as logged out.
     */
    public function markSessionAsLoggedOut(int $userId): bool
    {
        $activeSession = $this->getActiveSessionForUser($userId);

        if (! $activeSession) {
            return false;
        }

        $activeSession->markAsLoggedOut();

        return true;
    }

    /**
     * Get login history with session durations.
     */
    public function getLoginHistoryWithDurations(int $limit = 50, ?array $filters = null): Collection
    {
        return $this->getRecentLogins($limit, $filters);
    }
}
