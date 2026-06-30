<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Service to manage report and widget caching.
 *
 * Provides cache key constants and invalidation methods
 * for all reports and widgets across the application.
 */
class ReportCacheService
{
    // Cache key prefixes for reports
    public const MEMBER_STATS = 'reports:member_stats:gym:';

    public const MEMBER_SUMMARY = 'reports:member_summary:gym:';

    public const MEMBER_SUMMARY_WILDCARD = 'reports:member_summary:gym:';

    public const MEMBER_GROWTH = 'reports:member_growth:gym:';

    public const MEMBER_PLAN_DISTRIBUTION = 'reports:member_plan_distribution:gym:';

    public const CHURN_STATS = 'reports:churn_stats:gym:';

    public const REVENUE_STATS = 'reports:revenue_stats:gym:';

    public const REVENUE_SUMMARY = 'reports:revenue_summary:gym:';

    public const REVENUE_TRENDS = 'reports:revenue_trends:gym:';

    public const FINANCIAL_SUMMARY = 'reports:financial_summary:gym:';

    public const PNL_DATA_V2 = 'reports:pnl_data_v2:gym:';

    public const REVENUE_BREAKDOWN = 'reports:revenue_breakdown:gym:';

    public const EXPENSE_BREAKDOWN = 'reports:expense_breakdown:gym:';

    public const ATTENDANCE_STATS = 'reports:attendance_stats:gym:';

    public const ATTENDANCE_SUMMARY = 'reports:attendance_summary:gym:';

    public const HOURLY_DISTRIBUTION = 'reports:hourly_distribution:gym:';

    public const LEAD_STATS = 'reports:lead_stats:gym:';

    public const LEAD_SUMMARY = 'reports:lead_summary:gym:';

    public const LEAD_SCORING_STATS = 'reports:lead_scoring_stats:gym:';

    public const TRAINER_STATS = 'reports:trainer_stats:gym:';

    public const TRAINER_SUMMARY = 'reports:trainer_summary:gym:';

    public const TRAINER_PERFORMANCE = 'reports:trainer_performance:gym:';

    public const TRAINER_DISTRIBUTION = 'reports:trainer_distribution:gym:';

    public const EXPENSE_SUMMARY = 'reports:expense_summary:gym:';

    public const EQUIPMENT_USAGE = 'reports:equipment_usage:gym:';

    public const INSIGHTS = 'reports:insights:gym:';

    public const COHORT = 'reports:cohort:gym:';

    // Cache key prefixes for widgets
    public const WIDGET_MEMBER_GROWTH = 'widget:member_growth:gym:';

    public const WIDGET_MEMBER_STATUS = 'widget:member_status:gym:';

    public const WIDGET_MEMBER_PLAN_DIST = 'widget:member_plan_dist:gym:';

    public const WIDGET_MEMBER_DEMOGRAPHICS = 'widget:member_demographics:gym:';

    public const WIDGET_REVENUE_TREND = 'widget:revenue_trend:gym:';

    public const WIDGET_REVENUE_BY_PLAN = 'widget:revenue_by_plan:gym:';

    public const WIDGET_PAYMENT_METHOD = 'widget:payment_method:gym:';

    public const WIDGET_LEAD_FUNNEL = 'widget:lead_funnel:gym:';

    public const WIDGET_LEAD_SOURCE = 'widget:lead_source:gym:';

    public const WIDGET_LEAD_CONVERSION = 'widget:lead_conversion:gym:';

    public const WIDGET_TRAINER_CLIENTS = 'widget:trainer_clients:gym:';

    public const WIDGET_TRAINER_UTILIZATION = 'widget:trainer_utilization:gym:';

    public const WIDGET_EXPENSE_CATEGORY = 'widget:expense_category:gym:';

    public const WIDGET_EXPENSE_PAYMENT_METHOD = 'widget:expense_payment_method:gym:';

    public const WIDGET_EXPENSE_MONTHLY_TREND = 'widget:expense_monthly_trend:gym:';

    // Cache key for gym health score
    public const GYM_HEALTH_SCORE = 'gym:health_score:';

    // Cache TTL constants (in seconds)
    public const CACHE_STALE_SECONDS = 3600 * 2; // 2 hours

    public const CACHE_EXPIRE_SECONDS = 86400 * 2; // 2 days

    /**
     * Invalidate all member-related report caches.
     */
    public function invalidateMemberReports(int $gymId): void
    {
        $patterns = [
            self::MEMBER_STATS . $gymId,
            self::MEMBER_SUMMARY . $gymId . ':*',
            self::MEMBER_GROWTH . $gymId . ':*',
            self::MEMBER_PLAN_DISTRIBUTION . $gymId . ':*',
            self::CHURN_STATS . $gymId,
            // Widget caches
            self::WIDGET_MEMBER_GROWTH . $gymId . ':*',
            self::WIDGET_MEMBER_STATUS . $gymId . ':*',
            self::WIDGET_MEMBER_PLAN_DIST . $gymId . ':*',
            self::WIDGET_MEMBER_DEMOGRAPHICS . $gymId . ':*',
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
            self::REVENUE_STATS . $gymId,
            self::REVENUE_SUMMARY . $gymId . ':*',
            self::REVENUE_TRENDS . $gymId . ':*',
            self::FINANCIAL_SUMMARY . $gymId . ':*',
            self::PNL_DATA_V2 . $gymId . ':*',
            self::REVENUE_BREAKDOWN . $gymId . ':*',
            self::EXPENSE_BREAKDOWN . $gymId . ':*',
            // Widget caches
            self::WIDGET_REVENUE_TREND . $gymId . ':*',
            self::WIDGET_REVENUE_BY_PLAN . $gymId . ':*',
            self::WIDGET_PAYMENT_METHOD . $gymId . ':*',
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
            self::ATTENDANCE_STATS . $gymId . '*',
            self::ATTENDANCE_SUMMARY . $gymId . ':*',
            self::HOURLY_DISTRIBUTION . $gymId . ':*',
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
            self::LEAD_STATS . $gymId,
            self::LEAD_SUMMARY . $gymId . ':*',
            self::LEAD_SCORING_STATS . $gymId,
            // Widget caches
            self::WIDGET_LEAD_FUNNEL . $gymId . ':*',
            self::WIDGET_LEAD_SOURCE . $gymId . ':*',
            self::WIDGET_LEAD_CONVERSION . $gymId . ':*',
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
            self::TRAINER_STATS . $gymId,
            self::TRAINER_SUMMARY . $gymId,
            self::TRAINER_SUMMARY . $gymId . ':*',
            self::TRAINER_PERFORMANCE . $gymId . ':*',
            self::TRAINER_DISTRIBUTION . $gymId,
            // Widget caches
            self::WIDGET_TRAINER_CLIENTS . $gymId,
            self::WIDGET_TRAINER_UTILIZATION . $gymId,
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate all expense-related report caches.
     */
    public function invalidateExpenseReports(int $gymId): void
    {
        $patterns = [
            self::EXPENSE_SUMMARY . $gymId . ':*',
            // Widget caches (used by ExpenseReports widgets)
            self::WIDGET_EXPENSE_CATEGORY . $gymId . ':*',
            self::WIDGET_EXPENSE_PAYMENT_METHOD . $gymId . ':*',
            self::WIDGET_EXPENSE_MONTHLY_TREND . $gymId . ':*',
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate all equipment-related report caches.
     */
    public function invalidateEquipmentReports(int $gymId): void
    {
        $patterns = [
            self::EQUIPMENT_USAGE . $gymId . ':*',
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate insights cache.
     */
    public function invalidateInsights(int $gymId): void
    {
        $patterns = [
            self::INSIGHTS . $gymId,
        ];

        $this->clearCachePatterns($patterns);
    }

    /**
     * Invalidate gym health score cache.
     */
    public function invalidateGymHealthScore(int $gymId): void
    {
        Cache::forget(self::GYM_HEALTH_SCORE . $gymId);
    }

    /**
     * Invalidate cohort retention data cache.
     */
    public function invalidateCohortData(int $gymId): void
    {
        $patterns = [
            self::COHORT . $gymId . ':*',
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
        $redisPrefix = config('database.redis.options.prefix', '');

        // $keys = Cache::getRedis()->keys('widget:member_growth:gym:39:*');
        foreach ($patterns as $pattern) {
            // Check if this is a pattern (contains wildcard) or exact key
            if (str_contains($pattern, '*')) {
                // Pattern matching - use Redis KEYS
                if (Cache::getStore() instanceof RedisStore) {
                    $redis = Redis::connection('cache');
                    $fullPrefix = $this->getCachePrefix();

                    // Search for keys matching the pattern
                    $fullPattern = $fullPrefix . $pattern;
                    $keys = $redis->keys($fullPattern);

                    // Also search for flexible cache metadata keys
                    $flexiblePattern = $fullPrefix . 'illuminate:cache:flexible:created:' . str_replace('*', '', $pattern) . '*';
                    $flexibleKeys = $redis->keys($flexiblePattern);

                    $allKeys = array_merge($keys, $flexibleKeys);

                    foreach ($allKeys as $key) {
                        // Remove the prefix to get the cache key
                        $cacheKey = str_replace($redisPrefix . $fullPrefix, '', $key);

                        Cache::forget($cacheKey);

                        Cache::forget('illuminate:cache:flexible:created:' . $cacheKey);
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
        // $redisPrefix = config('database.redis.options.prefix', '');
        $cachePrefix = config('cache.prefix', '');

        return $cachePrefix;
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
