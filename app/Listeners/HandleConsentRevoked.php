<?php

namespace App\Listeners;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Events\BiometricConsentRevoked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleConsentRevoked implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * Uses exponential backoff: 60s, 180s, 300s (1min, 3min, 5min)
     */
    public array $backoff = [60, 180, 300];

    /**
     * Determine if the job should be executed after all database transactions commit.
     */
    public bool $afterCommit = true;

    public function __construct(
        private BiometricDataDeletionService $deletionService
    ) {}

    public function handle(BiometricConsentRevoked $event): void
    {
        // Delete all biometric templates for this member
        $this->deletionService->deleteForGymMember($event->gymMember);
    }
}
