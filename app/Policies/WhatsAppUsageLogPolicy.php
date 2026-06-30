<?php

declare(strict_types=1);

namespace App\Policies;

class WhatsAppUsageLogPolicy extends BasePolicy
{
    protected static string $tableName = 'whatsapp_usage_log';
}
