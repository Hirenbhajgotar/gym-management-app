<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DefaultRole;
use App\Models\AdmsDeviceCommand;
use App\Models\User;

class AdmsDeviceCommandPolicy extends BasePolicy
{
    protected static string $tableName = 'adms_device_command';

    public function viewAny(User $user): bool
    {
        return $user->can(static::getPermissionName('view_any')) || $user->hasAnyRole([
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::SUPER_ADMIN->value,
        ]);
    }

    public function view(User $user, mixed $model): bool
    {
        if (! $this->belongsToUserGym($user, $model)) {
            return false;
        }

        return $user->can(static::getPermissionName('view')) || $user->hasAnyRole([
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::SUPER_ADMIN->value,
        ]);
    }

    public function update(User $user, mixed $model): bool
    {
        if (! $this->belongsToUserGym($user, $model)) {
            return false;
        }

        return $user->can(static::getPermissionName('update')) || $user->hasAnyRole([
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::SUPER_ADMIN->value,
        ]);
    }

    public function resend(User $user, mixed $command = null): bool
    {
        if ($command !== null && ! $this->belongsToUserGym($user, $command)) {
            return false;
        }

        return $user->can(static::getPermissionName('update')) || $user->hasAnyRole([
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::SUPER_ADMIN->value,
        ]);
    }

    public function delete(User $user, mixed $model): bool
    {
        if (! $this->belongsToUserGym($user, $model)) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    protected function belongsToUserGym(User $user, mixed $model): bool
    {
        if ($user->hasRole(DefaultRole::SUPER_ADMIN->value)) {
            return true;
        }

        if (! $model instanceof AdmsDeviceCommand) {
            return true;
        }

        $userGymIds = $user->relationLoaded('gyms')
            ? $user->gyms->pluck('id')->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all()
            : $user->gyms()->pluck('gyms.id')->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();

        return in_array($model->gym_id, $userGymIds, true);
    }
}
