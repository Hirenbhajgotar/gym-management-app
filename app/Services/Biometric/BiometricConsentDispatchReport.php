<?php

declare(strict_types=1);

namespace App\Services\Biometric;

/**
 * Aggregate outcome of one biometric consent broadcast, not a per-member record.
 *
 * A refused authorization and an ineligible member are separate facts with
 * separate consequences — a member selected from another gym is the only signal
 * an operator has that a crafted selection was refused — so reporting them as one
 * "skipped" number hides it behind routine eligibility. notifyQueued() therefore
 * reads the two counters directly; there is deliberately no total-skipped
 * accessor to route it back through.
 */
final readonly class BiometricConsentDispatchReport
{
    public function __construct(
        public int $selected,
        public int $dispatched,
        public int $ineligible,
        public int $unauthorized,
    ) {}
}
