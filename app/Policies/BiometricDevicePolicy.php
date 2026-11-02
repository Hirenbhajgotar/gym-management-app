<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BiometricDevice;
use App\Models\User;

class BiometricDevicePolicy extends BasePolicy
{
    protected static string $tableName = 'biometric_device';

    public function rotateCredentials(User $user, ?BiometricDevice $device = null): bool
    {
        return $user->can(static::getPermissionName('update'));
    }

    /**
     * Deleting a terminal can strand credentials in its flash, so the
     * permission is necessary but not sufficient: the record must also belong to
     * one of the user's gyms. The panel already scopes the table to the selected
     * tenant, but a permission-only policy would still allow a gym-A operator to
     * delete a gym-B device if a record ever reached the gate.
     */
    public function delete(User $user, mixed $model): bool
    {
        if ($model instanceof BiometricDevice && ! $this->userCanActOnGym($user, $model->gym_id)) {
            return false;
        }

        return parent::delete($user, $model);
    }
}
