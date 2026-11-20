<?php

declare(strict_types=1);

namespace App\Biometrics\ValueObjects;

/**
 * Typed report of one stale-lease reclaim sweep.
 *
 * The counts are the transitions the ledger actually performed, so the console
 * adapter can report them without re-querying or re-deriving state. A concurrent
 * compare-and-set loser is a benign miss, counted separately and never raised.
 */
final readonly class ReclaimSummary
{
    public function __construct(
        public int $reclaimed = 0,
        public int $failed = 0,
        public int $orphaned = 0,
        public int $skipped = 0,
    ) {}

    /**
     * Commands whose expired lease was cleared and that returned to PENDING.
     */
    public function reclaimedCount(): int
    {
        return $this->reclaimed;
    }

    /**
     * Commands that exhausted their delivery budget and were terminalised as FAILED.
     */
    public function failedCount(): int
    {
        return $this->failed;
    }

    /**
     * Orphan rows of a deleted terminal that were terminalised as CANCELLED.
     */
    public function orphanedCount(): int
    {
        return $this->orphaned;
    }

    /**
     * Rows that lost a compare-and-set race and were left to the winning writer.
     */
    public function concurrentMisses(): int
    {
        return $this->skipped;
    }
}
