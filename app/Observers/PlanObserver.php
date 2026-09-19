<?php

declare(strict_types=1);

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class PlanObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Plan "created" event.
     */
    public function created(Plan $plan): void
    {
        $this->snapshotPrice($plan);
        $this->clearCache($plan);
    }

    /**
     * Handle the Plan "updated" event.
     */
    public function updated(Plan $plan): void
    {
        if ($plan->wasChanged(['price_monthly', 'price_yearly', 'currency'])) {
            $this->snapshotPrice($plan);
        }

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
        ParserHelper::clearCache(Plan::ALL_ENTERPRISE_PLANS_FOR_HOME_CACHE_KEY);
        ParserHelper::clearCache(Plan::PROFILE_ALL_PLANS_CACHE_KEY);
        ParserHelper::clearCache(Plan::NAVIGATION_BADGE_CACHE_KEY);
    }

    /**
     * Close the currently-open price row and open a new one reflecting the
     * plan's current price/currency. This preserves an audit trail so renewals
     * can always resolve the price that was in effect at a given time.
     */
    protected function snapshotPrice(Plan $plan): void
    {
        $now = now();

        PlanPrice::query()->where('plan_id', $plan->id)
            ->whereNull('effective_to')
            ->update(['effective_to' => $now]);

        PlanPrice::query()->create([
            'plan_id' => $plan->id,
            'currency' => $plan->currency,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'effective_from' => $now,
            'effective_to' => null,
        ]);
    }
}
