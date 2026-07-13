<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\Reports\ExpenseStatsUpdated;
use App\Events\Reports\RevenueStatsUpdated;
use App\Helpers\ParserHelper;
use App\Models\Expense;
use App\Services\DashboardCacheService;
use App\Services\ReportCacheService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ExpenseObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected ReportCacheService $reportCacheService,
        protected DashboardCacheService $dashboardCacheService
    ) {}

    /**
     * Handle the Expense "created" event.
     */
    public function created(Expense $expense): void
    {
        // Set next due date for recurring expenses
        if ($expense->is_recurring && $expense->recurring_frequency && ! $expense->next_due_date) {
            $expense->updateNextDueDate();
        }

        $this->clearCache($expense);
    }

    /**
     * Handle the Expense "updated" event.
     */
    public function updated(Expense $expense): void
    {
        // If status changed to approved, update next due date for recurring
        if ($expense->wasChanged('status') && $expense->isApproved() && $expense->is_recurring) {
            $expense->updateNextDueDate();
        }

        $this->clearCache($expense);
    }

    /**
     * Handle the Expense "deleted" event.
     */
    public function deleted(Expense $expense): void
    {
        $this->clearCache($expense);
    }

    /**
     * Handle the Expense "restored" event.
     */
    public function restored(Expense $expense): void
    {
        $this->clearCache($expense);
    }

    /**
     * Handle the Expense "force deleted" event.
     */
    public function forceDeleted(Expense $expense): void
    {
        $this->clearCache($expense);
    }

    private function clearCache(Expense $expense): void
    {
        $gymId = $expense->gym_id;

        ParserHelper::clearCache(Expense::NAVIGATION_BADGE_CACHE_KEY . $gymId);

        if ($gymId) {
            // Invalidate revenue reports (expenses affect P&L)
            $this->reportCacheService->invalidateRevenueReports($gymId);
            $this->reportCacheService->invalidateExpenseReports($gymId);
            $this->reportCacheService->invalidateInsights($gymId);

            // Invalidate dashboard widget caches
            $this->dashboardCacheService->invalidateExpenseWidgets($gymId);

            // Broadcast real-time events
            broadcast(new RevenueStatsUpdated($gymId));
            broadcast(new ExpenseStatsUpdated($gymId));
        }
    }
}
