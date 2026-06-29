<?php

declare(strict_types=1);

namespace App\Policies;

class GymMemberPolicy extends BasePolicy
{
    protected static string $tableName = 'gym_member';
}
