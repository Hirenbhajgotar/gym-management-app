<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AdmsCommandStatusEnum: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case ACKNOWLEDGED = 'acknowledged';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SENT => 'Sent',
            self::ACKNOWLEDGED => 'Acknowledged',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::SENT => 'info',
            self::ACKNOWLEDGED => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isSent(): bool
    {
        return $this === self::SENT;
    }

    public function isAcknowledged(): bool
    {
        return $this === self::ACKNOWLEDGED;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }
}
