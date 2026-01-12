<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\Plan;

class PlanObserver
{
    /**
     * Handle the Plan "created" event.
     */
    public function created(Plan $plan): void
    {
        $this->clearCache($plan);
    }

    /**
     * Handle the Plan "updated" event.
     */
    public function updated(Plan $plan): void
    {
        $this->clearCache($plan);
    }

    /**
     * Handle the Plan "deleted" event.
     */
    public function deleted(Plan $plan): void
    {
        $this->clearCache($plan);
    }

    /**
     * Handle the Plan "restored" event.
     */
    public function restored(Plan $plan): void
    {
        $this->clearCache($plan);
    }

    /**
     * Handle the Plan "force deleted" event.
     */
    public function forceDeleted(Plan $plan): void
    {
        $this->clearCache($plan);
    }

    protected function clearCache(Plan $plan): void
    {
        ParserHelper::clearCache(Plan::ALL_PLANS_FOR_HOME_CACHE_KEY);
        ParserHelper::clearCache(Plan::PROFILE_ALL_PLANS_CACHE_KEY);
        ParserHelper::clearCache(Plan::NAVIGATION_BADGE_CACHE_KEY);
    }
}
