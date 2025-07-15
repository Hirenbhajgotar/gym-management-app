<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Gym;
use Illuminate\Auth\Access\HandlesAuthorization;

class GymPolicy extends BasePolicy
{
    protected static string $tableName = 'gym';
}
