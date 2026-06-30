<?php

declare(strict_types=1);

namespace App\Policies;

class TrainerAttendancePolicy extends BasePolicy
{
    protected static string $tableName = 'trainer_attendance';
}
