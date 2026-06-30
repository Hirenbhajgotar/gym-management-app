<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\GymMember;
use App\Models\MemberPayment;
use Illuminate\Console\Command;

class CheckMembershipExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gym:check-membership-expiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired memberships and update status to expired';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for expired memberships...');

        $today = today();

        // Update Gym Members
        $expiredMembersCount = 0;

        GymMember::query()
            ->where('membership_end', '<', $today)
            ->where('payment_status', '!=', PaymentStatus::EXPIRED)
            ->chunkById(100, function ($members) use (&$expiredMembersCount) {
                foreach ($members as $member) {
                    $member->update(['payment_status' => PaymentStatus::EXPIRED]);
                    $expiredMembersCount++;
                }
            });

        $this->info("Updated {$expiredMembersCount} gym members to expired status.");

        // Update Member Payments
        $expiredPaymentsCount = 0;

        MemberPayment::query()
            ->where('membership_end', '<', $today)
            ->where('status', '!=', PaymentStatus::EXPIRED)
            ->chunkById(100, function ($payments) use (&$expiredPaymentsCount) {
                foreach ($payments as $payment) {
                    $payment->update(['status' => PaymentStatus::EXPIRED]);
                    $expiredPaymentsCount++;
                }
            });

        $this->info("Updated {$expiredPaymentsCount} member payments to expired status.");

        $this->info('Membership expiry check completed.');

        return self::SUCCESS;
    }
}
