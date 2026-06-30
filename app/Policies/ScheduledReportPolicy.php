<?php

declare(strict_types=1);

namespace App\Policies;

class ScheduledReportPolicy extends BasePolicy
{
    protected static string $tableName = 'scheduled_report';
}
