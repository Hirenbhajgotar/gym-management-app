<?php

namespace App\Policies;

use App\Models\User;
use App\Models\GymMember;
use Illuminate\Auth\Access\HandlesAuthorization;

class GymMemberPolicy extends BasePolicy
{
    protected static string $tableName = 'gym_member';
}
