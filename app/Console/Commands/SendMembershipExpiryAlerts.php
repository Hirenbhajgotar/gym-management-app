<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GymMember;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

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
     * Day offsets (relative to today) for which to send expiry alerts.
     *
     * @var array<int>
     */
    private const DAY_OFFSETS = [7, 0, -3];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = today();
        $totalNotified = 0;

        $this->info('Sending membership expiry alerts...');

        try {
            foreach (self::DAY_OFFSETS as $offset) {
                $count = $this->notifyForOffset($offset, $today);
                $totalNotified += $count;

                $label = $offset > 0
                    ? "{$offset} days before"
                    : ($offset === 0
                        ? 'on the day'
                        : ($offset * -1) . ' days after');

                $this->info("  {$label}: {$count} notified");
            }

            $this->info("Total: {$totalNotified} members notified");
            $this->info('Expiry alerts queued.');

            Log::info('Membership expiry alerts completed', [
                'total_notified' => $totalNotified,
                'offsets' => self::DAY_OFFSETS,
                'date' => $today->toDateString(),
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Membership expiry alerts failed: {$e->getMessage()}");
            Log::error('Membership expiry alerts failed', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Notify members whose membership ends on the given day offset.
     */
    private function notifyForOffset(int $offset, Carbon $today): int
    {
        $targetDate = $today->clone()->addDays($offset);
        $notified = 0;

        GymMember::query()
            ->notFrozen()
            ->membershipEndingOn($targetDate)
            ->with(['gym'])
            ->chunkById(200, function ($members) use ($offset, &$notified) {
                foreach ($members as $member) {
                    $cacheKey = "expiry_alert_{$member->id}_" . today()->toDateString();

                    if (! Cache::add($cacheKey, true, now()->addDay())) {
                        Log::info('Skipping duplicate expiry alert', [
                            'member_id' => $member->id,
                            'offset' => $offset,
                        ]);

                        continue;
                    }

                    try {
                        $member->notifyExpiry($offset);
                        $notified++;
                    } catch (Throwable $e) {
                        Log::error('Failed to send expiry alert to member', [
                            'member_id' => $member->id,
                            'offset' => $offset,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $notified;
    }
}
