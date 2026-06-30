<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    protected static string $tableName;

    public static function getTableName(): string
    {
        return static::$tableName;
    }

    /**
     * Get the permission name for a given action
     */
    protected static function getPermissionName(string $action): string
    {
        return $action . '_' . static::$tableName;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(static::getPermissionName('view_any'));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, mixed $model): bool
    {
        return $user->can(static::getPermissionName('view'));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(static::getPermissionName('create'));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, mixed $model): bool
    {
        return $user->can(static::getPermissionName('update'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, mixed $model): bool
    {
        return $user->can(static::getPermissionName('delete'));
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can(static::getPermissionName('delete_any'));
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, mixed $model): bool
    {
        return $user->can(static::getPermissionName('force_delete'));
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can(static::getPermissionName('force_delete_any'));
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, mixed $model): bool
    {
        return $user->can(static::getPermissionName('restore'));
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can(static::getPermissionName('restore_any'));
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, mixed $model): bool
    {
        return $user->can(static::getPermissionName('replicate'));
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can(static::getPermissionName('reorder'));
    }
}
