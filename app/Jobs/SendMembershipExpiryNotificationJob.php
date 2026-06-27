<?php

namespace App\Jobs;

use Illuminate\Foundation\Queue\Queueable;
use App\Models\GymMember;
use Illuminate\Contracts\Queue\ShouldQueue;

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
