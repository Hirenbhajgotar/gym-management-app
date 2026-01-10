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
        $this->clearCache();
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Lead "deleted" event.
     */
    public function deleted(Lead $lead): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Lead "restored" event.
     */
    public function restored(Lead $lead): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Lead "force deleted" event.
     */
    public function forceDeleted(Lead $lead): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        if (auth()->check()) {
            ParserHelper::clearCache('lead_navigation_badge_count_' . auth()->id());
        }
    }
}
