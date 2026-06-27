<?php

namespace App\Jobs;

use App\Models\GymMember;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMembershipExpiryNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GymMember $member,
        public int $daysLeft
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->member->notifyExpiry($this->daysLeft);
    }
}
