<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ReportCacheService
{
    /**
     * Invalidate all member-related report caches.
     */
    public function invalidateMemberReports(int $gymId): void
    {
        $patterns = [
            "reports:member_stats:gym:{$gymId}",
            "reports:member_summary:gym:{$gymId}:*",
            "reports:member_growth:gym:{$gymId}:*",
            "reports:member_plan_distribution:gym:{$gymId}:*",
            "reports:churn_stats:gym:{$gymId}",
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate all revenue-related report caches.
     */
    public function invalidateRevenueReports(int $gymId): void
    {
        $patterns = [
            "reports:revenue_stats:gym:{$gymId}",
            "reports:revenue_summary:gym:{$gymId}:*",
            "reports:revenue_trends:gym:{$gymId}:*",
            "reports:financial_summary:gym:{$gymId}:*",
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate all attendance-related report caches.
     */
    public function invalidateAttendanceReports(int $gymId): void
    {
        $patterns = [
            "reports:attendance_stats:gym:{$gymId}*",
            "reports:attendance_summary:gym:{$gymId}:*",
            "reports:hourly_distribution:gym:{$gymId}:*",
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate all lead-related report caches.
     */
    public function invalidateLeadReports(int $gymId): void
    {
        // Use exact cache keys that match what's used in the report pages
        $keys = [
            "reports:lead_stats:gym:{$gymId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Invalidate all trainer-related report caches.
     */
    public function invalidateTrainerReports(int $gymId): void
    {
        $patterns = [
            "reports:trainer_stats:gym:{$gymId}",
            "reports:trainer_summary:gym:{$gymId}:*",
            "reports:trainer_performance:gym:{$gymId}:*",
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate all expense-related report caches.
     */
    public function invalidateExpenseReports(int $gymId): void
    {
        $patterns = [
            "reports:expense_summary:gym:{$gymId}:*",
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate all equipment-related report caches.
     */
    public function invalidateEquipmentReports(int $gymId): void
    {
        $patterns = [
            "reports:equipment_usage:gym:{$gymId}:*",
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate insights cache.
     */
    public function invalidateInsights(int $gymId): void
    {
        $patterns = [
            "reports:insights:gym:{$gymId}",
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate all report caches for a gym.
     */
    public function invalidateAllReports(int $gymId): void
    {
        $this->invalidateMemberReports($gymId);
        $this->invalidateRevenueReports($gymId);
        $this->invalidateAttendanceReports($gymId);
        $this->invalidateLeadReports($gymId);
        $this->invalidateTrainerReports($gymId);
        $this->invalidateExpenseReports($gymId);
        $this->invalidateEquipmentReports($gymId);
        $this->invalidateInsights($gymId);
    }

    /**
     * Clear cache keys matching patterns.
     */
    protected function clearCachePatterns(array $patterns): void
    {
        foreach ($patterns as $pattern) {
            // For Redis, we can use the key pattern
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $keys = Cache::getRedis()->keys($this->getCachePrefix() . $pattern);

                if (! empty($keys)) {
                    foreach ($keys as $key) {
                        // Remove the prefix before forgetting
                        $keyWithoutPrefix = str_replace($this->getCachePrefix(), '', $key);
                        Cache::forget($keyWithoutPrefix);
                    }
                }
            } else {
                // For other cache drivers, just forget the exact key
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Get the cache key prefix from config.
     */
    protected function getCachePrefix(): string
    {
        return config('cache.prefix') ? config('cache.prefix') . ':' : '';
    }
}
