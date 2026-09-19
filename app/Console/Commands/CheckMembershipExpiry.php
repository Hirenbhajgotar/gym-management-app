<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\GymMember;
use App\Models\MemberPayment;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckMembershipExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gym:check-membership-expiry
                           {--dry-run : Simulate the operation without making any changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired memberships and update payment status to expired';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for expired memberships...');
        Log::info('Starting membership expiry check');

        try {
            $unfrozenCount = $this->autoUnfreezeExpiredFreezes();
            $expiredMembersCount = $this->markExpiredGymMembers();
            $expiredPaymentsCount = $this->markExpiredMemberPayments();

            $this->newLine();
            $this->info('Membership expiry check completed.');

            Log::info('Membership expiry check completed', [
                'auto_unfrozen_members' => $unfrozenCount,
                'expired_members' => $expiredMembersCount,
                'expired_payments' => $expiredPaymentsCount,
                'dry_run' => $this->option('dry-run'),
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Membership expiry check failed: {$e->getMessage()}");
            Log::error('Membership expiry check failed', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return self::FAILURE;
        }
    }

    private function autoUnfreezeExpiredFreezes(): int
    {
        $query = GymMember::query()
            ->where('is_frozen', true)
            ->whereNotNull('frozen_until')
            ->where('frozen_until', '<', now());

        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired member freezes found.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY-RUN] {$count} frozen gym member(s) would be auto-unfrozen.");

            return $count;
        }

        $unfrozenCount = 0;
        $query->chunkById(100, function ($members) use (&$unfrozenCount) {
            foreach ($members as $member) {
                try {
                    if ($member->autoUnfreezeIfExpired()) {
                        $unfrozenCount++;
                    }
                } catch (Throwable $e) {
                    Log::warning("Failed to auto-unfreeze member ID {$member->id}: {$e->getMessage()}", [
                        'member_id' => $member->id,
                        'exception' => $e,
                    ]);
                }
            }
        });

        $this->info("Auto-unfrozen {$unfrozenCount} gym member(s).");

        return $unfrozenCount;
    }

    private function markExpiredGymMembers(): int
    {
        $query = GymMember::query()
            ->membershipExpired()
            ->where(function ($q) {
                $q->where('payment_status', '!=', PaymentStatus::EXPIRED)
                    ->orWhereNull('payment_status');
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('No gym members with expired memberships found.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY-RUN] {$count} gym member(s) would be marked as expired.");

            return $count;
        }

        $updatedCount = 0;
        $query->chunkById(100, function ($members) use (&$updatedCount) {
            foreach ($members as $member) {
                $member->update(['payment_status' => PaymentStatus::EXPIRED]);
                $updatedCount++;
            }
        });

        $this->info("Updated {$updatedCount} gym member(s) to expired status.");

        return $updatedCount;
    }

    private function markExpiredMemberPayments(): int
    {
        $today = today();

        $query = MemberPayment::query()
            ->where('membership_end', '<', $today)
            ->where(function (Builder $q) {
                $q->where('status', '!=', PaymentStatus::EXPIRED)
                    ->orWhereNull('status');
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('No member payments with expired memberships found.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY-RUN] {$count} member payment(s) would be marked as expired.");

            return $count;
        }

        $updatedCount = 0;
        $query->chunkById(100, function ($payments) use (&$updatedCount) {
            foreach ($payments as $payment) {
                $payment->update(['status' => PaymentStatus::EXPIRED]);
                $updatedCount++;
            }
        });

        $this->info("Updated {$updatedCount} member payment(s) to expired status.");

        return $updatedCount;
    }
}
