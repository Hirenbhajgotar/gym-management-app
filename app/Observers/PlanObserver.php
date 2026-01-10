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
        $this->clearCache();
    }

    /**
     * Handle the Plan "updated" event.
     */
    public function updated(Plan $plan): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Plan "deleted" event.
     */
    public function deleted(Plan $plan): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Plan "restored" event.
     */
    public function restored(Plan $plan): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Plan "force deleted" event.
     */
    public function forceDeleted(Plan $plan): void
    {
        $this->clearCache();
    }

    protected function clearCache(): void
    {
        ParserHelper::clearCache('all_plans_for_home');
        ParserHelper::clearCache('profile_all_plans');

        if (auth()->check()) {
            ParserHelper::clearCache('plan_navigation_badge_count_' . auth()->id());
        }
    }
}
