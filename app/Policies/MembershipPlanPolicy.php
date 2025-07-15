<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MembershipPlan;
use Illuminate\Auth\Access\HandlesAuthorization;

class MembershipPlanPolicy extends BasePolicy
{
    protected static string $tableName = 'membership_plan';
}
