<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DefaultRole;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpensePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_expense');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Expense $expense): bool
    {
        if ($user->can('view_any_expense')) {
            return true;
        }

        // Users can view their own expenses
        return $user->can('view_expense') && $expense->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_expense');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Expense $expense): bool
    {
        // Only draft or rejected expenses can be edited
        if (! $expense->canEdit()) {
            return false;
        }

        // Owner can update any expense in their gym
        if ($user->hasRole(DefaultRole::GYM_OWNER->value)) {
            return $user->can('update_expense');
        }

        // Others can only update their own expenses
        return $user->can('update_expense') && $expense->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Expense $expense): bool
    {
        // Only draft expenses can be deleted, or by owner
        if ($user->hasRole(DefaultRole::GYM_OWNER->value)) {
            return $user->can('delete_expense');
        }

        return $user->can('delete_expense') && $expense->isDraft() && $expense->user_id === $user->id;
    }

    /**
     * Determine whether the user can approve the expense.
     */
    public function approve(User $user, Expense $expense): bool
    {
        // Cannot approve own expense (unless gym owner)
        if (! $user->hasRole(DefaultRole::GYM_OWNER->value) && $expense->user_id === $user->id) {
            return false;
        }

        // Check if expense can be approved
        if (! $expense->canApprove()) {
            return false;
        }

        // Manager approval limit check
        if ($user->hasRole(DefaultRole::MANAGER->value)) {
            $approvalLimit = config('expense.manager_approval_limit', 10000);
            if ($expense->amount > $approvalLimit) {
                return false;
            }
        }

        return $user->can('approve_expense');
    }

    /**
     * Determine whether the user can reject the expense.
     */
    public function reject(User $user, Expense $expense): bool
    {
        // Same logic as approve
        return $this->approve($user, $expense);
    }

    /**
     * Determine whether the user can mark expense as paid.
     */
    public function markAsPaid(User $user, Expense $expense): bool
    {
        return $user->can('approve_expense') && $expense->isApproved();
    }

    /**
     * Determine whether the user can export expenses.
     */
    public function export(User $user): bool
    {
        return $user->can('export_expense');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Expense $expense): bool
    {
        return $user->hasRole(DefaultRole::GYM_OWNER->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Expense $expense): bool
    {
        return $user->hasRole(DefaultRole::GYM_OWNER->value);
    }
}
