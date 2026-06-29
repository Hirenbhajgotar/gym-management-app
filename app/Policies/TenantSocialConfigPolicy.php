<?php

declare(strict_types=1);

namespace App\Policies;

class TenantSocialConfigPolicy extends BasePolicy
{
    protected static string $tableName = 'tenant_social_config';
}
