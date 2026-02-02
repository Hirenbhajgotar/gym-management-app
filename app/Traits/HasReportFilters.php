<?php

namespace App\Traits;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

/**
 * Trait for report pages with common caching and filtering functionality.
 */
trait HasReportFilters
{
    /**
     * Get the current tenant (gym) ID.
     */
    protected function getGymId(): ?int
    {
        return Filament::getTenant()?->id;
    }

    /**
     * Generate a cache key for report data.
     */
    protected function getReportCacheKey(string $reportType, array $filters = []): string
    {
        $gymId = $this->getGymId();
        $filterHash = md5(json_encode($filters));

        return "report:{$reportType}:gym:{$gymId}:filters:{$filterHash}";
    }

    /**
     * Get cached report data or compute and cache it.
     *
     * @param  string  $reportType  Unique identifier for the report
     * @param  callable  $callback  Function that computes the report data
     * @param  int  $ttlMinutes  Cache TTL in minutes (default: 15)
     */
    protected function getCachedReportData(string $reportType, callable $callback, int $ttlMinutes = 15): mixed
    {
        $cacheKey = $this->getReportCacheKey($reportType, $this->filters ?? []);

        return Cache::flexible(
            $cacheKey,
            [now()->addMinutes($ttlMinutes), now()->addMinutes($ttlMinutes * 2)],
            $callback
        );
    }

    /**
     * Clear the cache for a specific report type.
     */
    protected function clearReportCache(string $reportType): void
    {
        $cacheKey = $this->getReportCacheKey($reportType, $this->filters ?? []);
        Cache::forget($cacheKey);
    }

    /**
     * Format currency for display.
     */
    protected function formatCurrency(float|int $amount, string $currency = 'INR'): string
    {
        return \Illuminate\Support\Number::currency($amount, in: $currency);
    }

    /**
     * Format percentage for display.
     */
    protected function formatPercentage(float|int $value, int $decimals = 1): string
    {
        return number_format($value, $decimals) . '%';
    }

    /**
     * Calculate percentage change between two values.
     */
    protected function calculatePercentageChange(float|int $current, float|int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get color based on trend (positive/negative).
     */
    protected function getTrendColor(float|int $change): string
    {
        if ($change > 0) {
            return 'success';
        } elseif ($change < 0) {
            return 'danger';
        }

        return 'gray';
    }

    /**
     * Get trend icon based on change direction.
     */
    protected function getTrendIcon(float|int $change): string
    {
        if ($change > 0) {
            return 'heroicon-m-arrow-trending-up';
        } elseif ($change < 0) {
            return 'heroicon-m-arrow-trending-down';
        }

        return 'heroicon-m-minus';
    }
}
