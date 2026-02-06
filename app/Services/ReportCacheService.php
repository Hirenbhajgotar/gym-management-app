<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

        // Also invalidate related caches
        $this->invalidateGymHealthScore($gymId);
        $this->invalidateCohortData($gymId);
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

        // Revenue affects gym health score
        $this->invalidateGymHealthScore($gymId);
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

        // Attendance affects gym health score
        $this->invalidateGymHealthScore($gymId);
    }

    /**
     * Invalidate all lead-related report caches.
     */
    public function invalidateLeadReports(int $gymId): void
    {
        $patterns = [
            "reports:lead_stats:gym:{$gymId}",
        ];

        $this->clearCachePatterns($patterns);

        // Leads affect gym health score (conversion rate)
        $this->invalidateGymHealthScore($gymId);
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
     * Invalidate gym health score cache.
     */
    public function invalidateGymHealthScore(int $gymId): void
    {
        Cache::forget("gym:health_score:{$gymId}");
    }

    /**
     * Invalidate cohort retention data cache.
     */
    public function invalidateCohortData(int $gymId): void
    {
        $patterns = [
            "report:cohort:gym:{$gymId}:*",
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
        $this->invalidateGymHealthScore($gymId);
        $this->invalidateCohortData($gymId);
    }

    /**
     * Clear cache keys matching patterns.
     * For pattern matching (keys ending with *), uses Redis KEYS command.
     * For exact keys, uses Cache::forget directly.
     */
    protected function clearCachePatterns(array $patterns): void
    {
        foreach ($patterns as $pattern) {
            // Check if this is a pattern (contains wildcard) or exact key
            Log::info('Pattern: ' . $pattern);
            if (str_contains($pattern, '*')) {
                // Pattern matching - use Redis KEYS
                if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                    $redis = \Illuminate\Support\Facades\Redis::connection('cache');
                    $fullPrefix = $this->getCachePrefix();

                    // Search for keys matching the pattern
                    $fullPattern = $fullPrefix . $pattern;
                    $keys = $redis->keys($fullPattern);

                    // Also search for flexible cache metadata keys
                    $flexiblePattern = $fullPrefix . 'illuminate:cache:flexible:created:' . str_replace('*', '', $pattern) . '*';
                    $flexibleKeys = $redis->keys($flexiblePattern);

                    $allKeys = array_merge($keys, $flexibleKeys);

                    Log::info('All keys: ' . json_encode($allKeys));

                    if (! empty($allKeys)) {
                        foreach ($allKeys as $key) {
                            // Remove the prefix to get the cache key
                            $cacheKey = str_replace($fullPrefix, '', $key);
                            Cache::forget($cacheKey);
                        }
                    }
                }
            } else {
                // Exact key - just forget it directly
                Cache::forget($pattern);
                // Also forget the flexible cache metadata key
                Cache::forget('illuminate:cache:flexible:created:' . $pattern);
            }
        }
    }

    /**
     * Get the cache key prefix from config.
     *
     * Redis keys in Laravel are formatted as: {database.redis.options.prefix}{cache.prefix}{key}
     */
    protected function getCachePrefix(): string
    {
        $redisPrefix = config('database.redis.options.prefix', '');
        $cachePrefix = config('cache.prefix', '');

        return $redisPrefix . $cachePrefix;
    }
}
