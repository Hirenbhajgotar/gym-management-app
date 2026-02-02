<?php

namespace App\Observers;

use App\Enums\ExpenseStatus;
use App\Helpers\ParserHelper;
use App\Models\Expense;

class ExpenseObserver
{
    /**
     * Handle the Expense "creating" event.
     */
    public function creating(Expense $expense): void
    {
        // Set default currency if not provided
        if (empty($expense->currency)) {
            $expense->currency = 'INR';
        }

        // Auto-approve for gym owners
        if ($expense->user && $expense->shouldAutoApprove()) {
            $expense->status = ExpenseStatus::APPROVED;
            $expense->approved_by_id = $expense->user_id;
            $expense->approved_at = now();
        }
    }

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
        ParserHelper::clearCache(Expense::NAVIGATION_BADGE_CACHE_KEY . $expense->gym_id);
    }
}
