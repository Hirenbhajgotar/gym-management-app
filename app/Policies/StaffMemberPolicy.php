<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StaffMember;
use Illuminate\Auth\Access\HandlesAuthorization;

class StaffMemberPolicy
{
    protected static string $tableName = 'staff_member';
}
