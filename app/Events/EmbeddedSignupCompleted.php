<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmbeddedSignupCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WhatsAppAccount $whatsAppAccount,
        public string $wabaId,
        public ?string $phoneNumberId = null
    ) {}
}
