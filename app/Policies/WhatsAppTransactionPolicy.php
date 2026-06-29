<?php

declare(strict_types=1);

namespace App\Policies;

class WhatsAppTransactionPolicy extends BasePolicy
{
    // protected static string $tableName = 'whatsapp_transaction';
    // protected static string $tableName = 'whats_app_transaction';
    protected static string $tableName = 'whatsapp_credit_transaction';
}
