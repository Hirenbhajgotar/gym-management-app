<?php

declare(strict_types=1);

namespace App\Policies;

class GymMemberAttendancePolicy extends BasePolicy
{
    protected static string $tableName = 'gym_member_attendance';
}
