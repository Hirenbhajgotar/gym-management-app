<?php

namespace App\Observers;

use App\Models\ReportGoal;
use Illuminate\Support\Facades\Cache;

class ReportGoalObserver
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

    /**
     * Handle events after all transactions are committed.
     */
    public $afterCommit = true;

    protected function clearCache(ReportGoal $reportGoal): void
    {
        if ($reportGoal->gym_id) {
            Cache::forget(ReportGoal::CACHE_KEY_PREFIX . $reportGoal->gym_id);
        }
    }
}
