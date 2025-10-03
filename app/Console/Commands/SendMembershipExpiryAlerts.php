<?php

namespace App\Console\Commands;

use App\Models\GymMember;
use App\Notifications\MembershipExpiryNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendMembershipExpiryAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:send-membership-expiry-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send renewal reminders 7 days before, on the day, and 3 days after expiry';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = today();

        // 7 days before
        GymMember::query()
            ->with(['gym'])
            ->whereDate('membership_end', $now->clone()->addDays(7))
            ->chunk(200, fn($members) => $members->each->notifyExpiry(7));

        // 1 day before
        // GymMember::query()
        //     ->with(['gym'])
        //     ->whereDate('membership_end', $now->clone()->addDays(1))
        //     ->chunk(200, fn($members) => $members->each->notifyExpiry(1));

        // on the day
        $members = GymMember::query()
            ->with(['gym'])
            ->whereDate('membership_end', $now)
            ->chunk(200, fn($members) => $members->each->notifyExpiry(0));

        // 3 days after (grace)
        // GymMember::query()
        //     ->with(['gym'])
        //     ->whereDate('membership_end', $now->clone()->subDays(3))
        //     ->chunk(200, fn($members) => $members->each->notifyExpiry(-3));

        $this->info('Expiry alerts queued.');

        return self::SUCCESS;
    }
}
