<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendLowWhatsAppCreditNotificationJob;
use App\Models\WhatsAppMessageCredit;
use Illuminate\Console\Command;

class CheckLowWhatsAppCreditsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:check-low-credits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks for gyms with low WhatsApp credits and queues a notification.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $credits = WhatsAppMessageCredit::query()->where('is_low_credit_notified', false)->get();

        $count = 0;
        foreach ($credits as $credit) {
            if ($credit->getBalance() <= config('app.low_whatsapp_message_balance_threshold')) {
                dispatch(new SendLowWhatsAppCreditNotificationJob($credit));
                $count++;
            }
        }

        $this->info("Dispatched low credit notifications for {$count} gyms.");
    }
}
