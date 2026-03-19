<?php

namespace App\Events;

use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WabaCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WhatsAppAccount $whatsAppAccount,
        public string $wabaId
    ) {}
}
