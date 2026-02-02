<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\ExpenseBudget;

class ExpenseBudgetObserver
{
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
        ParserHelper::clearCache(ExpenseBudget::NAVIGATION_BADGE_CACHE_KEY . $expenseBudget->gym_id);
    }
}
