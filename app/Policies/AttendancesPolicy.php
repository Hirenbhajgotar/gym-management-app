<?php

namespace App\Policies;

use App\Models\User;

class AttendancesPolicy extends BasePolicy
{
    protected static string $tableName = 'attendance';

    /**
     * Determine whether the user can approve attendance.
     */
    public function approve(User $user): bool
    {
        return $user->can(static::getPermissionName('approve'));
    }
}
