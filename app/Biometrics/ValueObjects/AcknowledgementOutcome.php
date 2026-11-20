<?php

declare(strict_types=1);

namespace App\Biometrics\ValueObjects;

use App\Enums\AdmsCommandStatusEnum;

/**
 * Typed result of applying an acknowledgement to a ledger command.
 *
 * The interface is safe to call on any command state: a duplicate or stale reply
 * is ignored rather than overwriting a newer state. `applied` reports whether a
 * transition actually happened; `status` is the command's resulting status in
 * either case so adapters never need to re-query or inspect status themselves.
 */
final readonly class AcknowledgementOutcome
{
    private function __construct(
        public bool $applied,
        public AdmsCommandStatusEnum $status,
    ) {}

    public static function applied(AdmsCommandStatusEnum $status): self
    {
        return new self(true, $status);
    }

    public static function ignored(AdmsCommandStatusEnum $status): self
    {
        return new self(false, $status);
    }

    public function wasApplied(): bool
    {
        return $this->applied;
    }

    public function resultingStatus(): AdmsCommandStatusEnum
    {
        return $this->status;
    }

    /**
     * True only when this acknowledgement transitioned the command to acknowledged.
     *
     * Completion notifications (for example a reboot initiator) are driven from
     * this so a duplicate, stale or rejected reply never re-notifies anyone.
     */
    public function wasAppliedAndAcknowledged(): bool
    {
        return $this->applied && $this->status === AdmsCommandStatusEnum::ACKNOWLEDGED;
    }
}
