<?php

declare(strict_types=1);

namespace App\Policies;

class BiometricDevicePolicy extends BasePolicy
{
    protected static string $tableName = 'biometric_device';
}
