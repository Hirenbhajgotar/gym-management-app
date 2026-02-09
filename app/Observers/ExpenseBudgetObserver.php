<?php

namespace App\Observers;

use App\Events\Reports\ExpenseStatsUpdated;
use App\Helpers\ParserHelper;
use App\Models\ExpenseBudget;
use App\Services\ReportCacheService;

class ExpenseBudgetObserver
{
    public function __construct(
        protected ReportCacheService $reportCacheService
    ) {}

    /**
     * Handle the ExpenseBudget "created" event.
     */
    public function created(ExpenseBudget $expenseBudget): void
    {
        $this->clearCache($expenseBudget);
    }

    /**
     * Handle the ExpenseBudget "updated" event.
     */
    public function updated(ExpenseBudget $expenseBudget): void
    {
        $this->clearCache($expenseBudget);
    }

    /**
     * Handle the ExpenseBudget "deleted" event.
     */
    public function deleted(ExpenseBudget $expenseBudget): void
    {
        $this->clearCache($expenseBudget);
    }

    /**
     * Handle the ExpenseBudget "restored" event.
     */
    public function restored(ExpenseBudget $expenseBudget): void
    {
        $this->clearCache($expenseBudget);
    }

    /**
     * Handle the ExpenseBudget "force deleted" event.
     */
    public function forceDeleted(ExpenseBudget $expenseBudget): void
    {
        $this->clearCache($expenseBudget);
    }

    private function clearCache(ExpenseBudget $expenseBudget): void
    {
        $gymId = $expenseBudget->gym_id;

        ParserHelper::clearCache(ExpenseBudget::NAVIGATION_BADGE_CACHE_KEY . $gymId);

        if ($gymId) {
            // Invalidate expense reports cache
            $this->reportCacheService->invalidateExpenseReports($gymId);

            // Broadcast real-time update event
            broadcast(new ExpenseStatsUpdated($gymId));
        }
    }
}
