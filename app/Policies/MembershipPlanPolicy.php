<?php

declare(strict_types=1);

namespace App\Policies;

class MembershipPlanPolicy extends BasePolicy
{
    protected static string $tableName = 'membership_plan';
}
