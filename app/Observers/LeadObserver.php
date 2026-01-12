<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\Lead;

class LeadObserver
{
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
    }
}
