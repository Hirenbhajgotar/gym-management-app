<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ReportGoal;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Cache;

class ReportGoalObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the ReportGoal "created" event.
     */
    public function created(ReportGoal $reportGoal): void
    {
        $this->clearCache($reportGoal);
    }

    /**
     * Handle the ReportGoal "updated" event.
     */
    public function updated(ReportGoal $reportGoal): void
    {
        $this->clearCache($reportGoal);
    }

    /**
     * Handle the ReportGoal "deleted" event.
     */
    public function deleted(ReportGoal $reportGoal): void
    {
        $this->clearCache($reportGoal);
    }

    protected function clearCache(ReportGoal $reportGoal): void
    {
        if ($reportGoal->gym_id) {
            Cache::forget(ReportGoal::CACHE_KEY_PREFIX . $reportGoal->gym_id);
        }
    }
}
