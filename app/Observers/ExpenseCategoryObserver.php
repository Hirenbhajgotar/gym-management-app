<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\ExpenseCategory;

class ExpenseCategoryObserver
{
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
        ParserHelper::clearCache(ExpenseCategory::NAVIGATION_BADGE_CACHE_KEY . $expenseCategory->gym_id);
    }
}
