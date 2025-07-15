<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Plan;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlanPolicy extends BasePolicy
{
    protected static string $tableName = 'plan';
}
