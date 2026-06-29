<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Service to manage dashboard widget caching.
 *
 * Provides cache key constants and invalidation methods
 * for Admin dashboard widgets.
 */
class DashboardCacheService
{
    // Cache key prefixes for dashboard widgets
    public const COLLECTION_STATS = 'dashboard:collection_stats:gym:';

    public const EXPENSE_STATS = 'dashboard:expense_stats:gym:';

    public const SUBSCRIPTION_STATS = 'dashboard:subscription_stats:gym:';

    public const REVENUE_CHART = 'dashboard:revenue_chart:gym:';

    public const ATTENDANCE_STATS = 'dashboard:attendance_stats:gym:';

    // Cache TTL constants (in seconds)
    public const CACHE_STALE_SECONDS = 3600 * 2; // 2 hours

    public const CACHE_EXPIRE_SECONDS = 86400 * 2; // 2 days

    /**
     * Invalidate member-related dashboard widget caches.
     */
    public function invalidateMemberWidgets(int $gymId): void
    {
        $this->forgetFlexibleCache(self::SUBSCRIPTION_STATS . $gymId);
    }

    /**
     * Invalidate expense-related dashboard widget caches.
     */
    public function invalidateExpenseWidgets(int $gymId): void
    {
        $this->forgetFlexibleCache(self::EXPENSE_STATS . $gymId);
    }

    /**
     * Invalidate revenue-related dashboard widget caches.
     */
    public function invalidateRevenueWidgets(int $gymId): void
    {
        $this->forgetFlexibleCache(self::COLLECTION_STATS . $gymId);
        $this->forgetFlexibleCache(self::REVENUE_CHART . $gymId);
    }

    /**
     * Invalidate attendance-related dashboard widget caches.
     */
    public function invalidateAttendanceWidgets(int $gymId): void
    {
        $this->forgetFlexibleCache(self::ATTENDANCE_STATS . $gymId);
    }

    /**
     * Invalidate all dashboard widget caches for a gym.
     */
    public function invalidateAll(int $gymId): void
    {
        $this->invalidateMemberWidgets($gymId);
        $this->invalidateExpenseWidgets($gymId);
        $this->invalidateRevenueWidgets($gymId);
        $this->invalidateAttendanceWidgets($gymId);
    }

    /**
     * Forget a flexible cache key and its metadata.
     */
    protected function forgetFlexibleCache(string $key): void
    {
        Cache::forget($key);
        Cache::forget('illuminate:cache:flexible:created:' . $key);
    }

    /**
     * Get the cache TTL array for Cache::flexible().
     *
     * @return array{0: DateTimeInterface, 1: DateTimeInterface}
     */
    public static function getCacheTtl(): array
    {
        return [
            now()->addSeconds(self::CACHE_STALE_SECONDS),
            now()->addSeconds(self::CACHE_EXPIRE_SECONDS),
        ];
    }
}
