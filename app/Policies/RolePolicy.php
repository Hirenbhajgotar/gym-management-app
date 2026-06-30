<?php

declare(strict_types=1);

namespace App\Policies;

class RolePolicy extends BasePolicy
{
    protected static string $tableName = 'role';
}
