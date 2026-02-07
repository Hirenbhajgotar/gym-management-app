<?php

namespace App\Observers;

use App\Events\Reports\LeadStatsUpdated;
use App\Helpers\ParserHelper;
use App\Models\Lead;
use App\Models\ReportGoal;
use App\Services\ReportCacheService;
use Illuminate\Support\Facades\Cache;

class LeadObserver
{
    public function __construct(
        protected ReportCacheService $reportCacheService
    ) {}

    /**
     * Handle the Lead "created" event.
     */
    public function created(Lead $lead): void
    {
        $this->clearCache($lead);
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        $this->clearCache($lead);
    }

    /**
     * Handle the Lead "deleted" event.
     */
    public function deleted(Lead $lead): void
    {
        $this->clearCache($lead);
    }

    /**
     * Handle the Lead "restored" event.
     */
    public function restored(Lead $lead): void
    {
        $this->clearCache($lead);
    }

    /**
     * Handle the Lead "force deleted" event.
     */
    public function forceDeleted(Lead $lead): void
    {
        $this->clearCache($lead);
    }

    private function clearCache(Lead $lead): void
    {
        ParserHelper::clearCache(Lead::NAVIGATION_BADGE_CACHE_KEY . $lead->gym_id);
        ParserHelper::clearCache(Lead::DASHBOARD_CRM_STATS_WIDGET . $lead->gym_id);

        // Invalidate report caches
        $this->reportCacheService->invalidateLeadReports($lead->gym_id);
        $this->reportCacheService->invalidateInsights($lead->gym_id);

        // Invalidate GoalProgressWidget cache (for leads goal tracking)
        Cache::forget(ReportGoal::CACHE_KEY_PREFIX . $lead->gym_id);

        // Broadcast real-time event after transaction commits
        broadcast(new LeadStatsUpdated($lead->gym_id));
    }
}
