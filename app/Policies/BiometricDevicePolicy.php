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
}
