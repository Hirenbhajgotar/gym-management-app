<?php

declare(strict_types=1);

namespace App\Biometrics\ValueObjects;

use Illuminate\Support\Carbon;

/**
 * Typed report of the ledger transitions performed when a terminal is deleted.
 *
 * The device-deletion call owns every orphan branch and returns this outcome so
 * the adapter can write its audit entry from it instead of re-querying the ledger
 * or re-deriving which rows were cancelled, failed or preserved. The counts are
 * the transitions actually performed.
 */
final readonly class DeletionTerminalisation
{
    public function __construct(
        public int $cancelled,
        public int $failed,
        public int $preserved,
        public Carbon $recordedAt,
    ) {}

    /**
     * Undelivered rows terminalised as CANCELLED.
     */
    public function cancelledCount(): int
    {
        return $this->cancelled;
    }

    /**
     * Delivered, non-preserved rows terminalised as FAILED.
     */
    public function failedCount(): int
    {
        return $this->failed;
    }

    /**
     * Delivered `user_add` rows stamped and retained as durable evidence.
     */
    public function preservedCount(): int
    {
        return $this->preserved;
    }

    /**
     * Rows moved to a terminal state — the count the deletion audit records.
     *
     * A preserved row is deliberately excluded: it stays `SENT` and is reported
     * separately, so its evidence is never counted as a cancellation.
     */
    public function terminalisedCount(): int
    {
        return $this->cancelled + $this->failed;
    }

    /**
     * The moment the evidence marker was written, for the deletion audit timestamp.
     */
    public function evidenceRecordedAt(): Carbon
    {
        return $this->recordedAt;
    }
}
