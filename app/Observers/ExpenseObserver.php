<?php

namespace App\Observers;

use App\Enums\ExpenseStatus;
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
    }
}
