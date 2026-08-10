<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GymMember;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMembershipExpiryNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function uniqueId(): string
    {
        return "expiry_notification_{$this->member->id}_{$this->daysLeft}_" . today()->toDateString();
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GymMember $member,
        public int $daysLeft,
        public bool $forceSend = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = "expiry_alert_{$this->member->id}_" . today()->toDateString();

        if (! Cache::add($cacheKey, true, now()->addDay()) && ! $this->forceSend) {
            return;
        }

        try {
            $this->member->notifyExpiry($this->daysLeft);
        } catch (Throwable $th) {
            Log::error('SendMembershipExpiryNotificationJob failed', [
                'member_id' => $this->member->id,
                'days_left' => $this->daysLeft,
                'error' => $th->getMessage(),
            ]);

            throw $th;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SendMembershipExpiryNotificationJob failed after retries', [
            'member_id' => $this->member->id,
            'days_left' => $this->daysLeft,
            'error' => $exception->getMessage(),
        ]);
    }
}
