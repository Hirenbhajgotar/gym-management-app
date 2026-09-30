<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class AdmsDeviceCommandPolicy extends BasePolicy
{
    protected static string $tableName = 'adms_device_command';

    public function viewAny(User $user): bool
    {
        return $user->can(static::getPermissionName('view_any')) || $user->hasAnyRole(['gym_owner', 'manager', 'super_admin']);
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->can(static::getPermissionName('view')) || $user->hasAnyRole(['gym_owner', 'manager', 'super_admin']);
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->can(static::getPermissionName('update')) || $user->hasAnyRole(['gym_owner', 'manager', 'super_admin']);
    }

    public function resend(User $user, mixed $command = null): bool
    {
        return $user->can(static::getPermissionName('update')) || $user->hasAnyRole(['gym_owner', 'manager', 'super_admin']);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->hasRole('super_admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
