<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Events\BiometricConsentRevoked;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;

class HandleConsentRevoked implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * This is the *queue's* retry bound, not the erasure budget. The cloud-erasure budget is
     * measured in time (`biometrics.cloud_erasure_cooldown_hours`) precisely so this short,
     * fast backoff cannot exhaust it: three attempts in ~4 minutes would otherwise drive a
     * transient provider outage to the terminal `DeletionUnresolvable` state while the
     * member's erasure was reported complete. On the last attempt `handle()` hands the service
     * this position so the member's own erasure still completes instead of being discarded
     * with the job.
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

    /**
     * Calculate the time at which the job should expire.
     * Enforces a 1-hour retry window from the first attempt.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function __construct(
        private BiometricDataDeletionService $deletionService
    ) {}

    public function handle(BiometricConsentRevoked $event): void
    {
        // The queue's retry budget is a separate concern from the erasure obligation. On the
        // last attempt the member's own erasure (anonymising their biometric attendances and
        // deleting the consent row) must still complete rather than being abandoned with the
        // job: a provider outage that outlasts `$tries` otherwise strands the anonymise step
        // while the credential is erased later by the hourly reconciler. The outstanding
        // template obligations stay `PendingDeletion` and are reconciled from there.
        //
        // `finalAttempt` means "no retry will happen", which is true in three cases: there is
        // no job at all (direct invocation); the job ran on the `sync` connection, whose
        // `attempts()` is hard-coded to 1 and which never retries; or this is the last of the
        // queued retries. Without the `SyncJob` case a retryable failure on `sync` — which
        // `phpunit.xml` and `.env.testing` force — would always take the throw path and the
        // erasure would never be re-driven.
        $this->deletionService->deleteForGymMember(
            $event->gymMember,
            finalAttempt: $this->job === null
                || $this->job instanceof SyncJob
                || $this->attempts() >= $this->tries
        );
    }
}
