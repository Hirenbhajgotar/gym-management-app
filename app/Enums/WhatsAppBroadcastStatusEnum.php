<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppBroadcastStatusEnum: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case SENDING = 'sending';
    case COMPLETED = 'completed';
    case COMPLETED_WITH_ERRORS = 'completed_with_errors';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::SENDING => 'Sending',
            self::COMPLETED => 'Completed',
            self::COMPLETED_WITH_ERRORS => 'Completed with Errors',
            self::CANCELLED => 'Cancelled',
            self::FAILED => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SCHEDULED => 'blue',
            self::SENDING => 'warning',
            self::COMPLETED => 'success',
            self::COMPLETED_WITH_ERRORS => 'warning',
            self::CANCELLED => 'danger',
            self::FAILED => 'danger',
        };
    }

    /**
     * A broadcast is "non-counting" when it has not consumed a send slot yet
     * (never sent / failed / cancelled). These statuses are excluded from the
     * daily-quota count and cooldown calculation.
     */
    public function isNonCounting(): bool
    {
        return in_array($this, [self::DRAFT, self::CANCELLED, self::FAILED], true);
    }

    /**
     * A broadcast is "finished" once it has reached a terminal completed/cancelled
     * state and should not be dispatched again.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::COMPLETED_WITH_ERRORS, self::CANCELLED, self::FAILED], true);
    }

    /**
     * Compute terminal status from sent, failed, and target counts.
     */
    public static function fromCounts(int $sent, int $failed, int $target): self
    {
        if ($sent > 0 && $failed > 0) {
            return self::COMPLETED_WITH_ERRORS;
        }

        if ($sent > 0) {
            return self::COMPLETED;
        }

        if ($failed > 0 || $target > 0) {
            return self::FAILED;
        }

        return self::COMPLETED;
    }

    /**
     * @return array<int, string>
     */
    public static function nonCountingValues(): array
    {
        return [self::DRAFT->value, self::CANCELLED->value, self::FAILED->value];
    }
}
