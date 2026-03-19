<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageCredit;
use App\Notifications\LowWhatsAppCreditNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendLowWhatsAppCreditNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WhatsAppMessageCredit $credit
    ) {}

    public function handle(): void
    {
        $owner = $this->credit->gym->owner();

        if ($owner) {
            $owner->notify(new LowWhatsAppCreditNotification($this->credit));
        } else {
            Log::warning("No owner found for gym ID {$this->credit->gym_id} while sending low WhatsApp credit notification.");
        }

        $this->credit->update(['is_low_credit_notified' => true]);
    }
}
