<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\Reports\ExpenseStatsUpdated;
use App\Helpers\ParserHelper;
use App\Models\ExpenseCategory;
use App\Services\ReportCacheService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ExpenseCategoryObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected ReportCacheService $reportCacheService
    ) {}

    /**
     * Handle the ExpenseCategory "created" event.
     */
    public function created(ExpenseCategory $expenseCategory): void
    {
        $this->clearCache($expenseCategory);
    }

    /**
     * Handle the ExpenseCategory "updated" event.
     */
    public function updated(ExpenseCategory $expenseCategory): void
    {
        $this->clearCache($expenseCategory);
    }

    /**
     * Handle the ExpenseCategory "deleted" event.
     */
    public function deleted(ExpenseCategory $expenseCategory): void
    {
        $this->clearCache($expenseCategory);
    }

    /**
     * Handle the ExpenseCategory "restored" event.
     */
    public function restored(ExpenseCategory $expenseCategory): void
    {
        $this->clearCache($expenseCategory);
    }

    /**
     * Handle the ExpenseCategory "force deleted" event.
     */
    public function forceDeleted(ExpenseCategory $expenseCategory): void
    {
        $this->clearCache($expenseCategory);
    }

    private function clearCache(ExpenseCategory $expenseCategory): void
    {
        $gymId = $expenseCategory->gym_id;

        ParserHelper::clearCache(ExpenseCategory::NAVIGATION_BADGE_CACHE_KEY . $gymId);

        if ($gymId) {
            // Invalidate expense reports cache
            $this->reportCacheService->invalidateExpenseReports($gymId);

            // Broadcast real-time update event
            broadcast(new ExpenseStatsUpdated($gymId));
        }
    }
}
