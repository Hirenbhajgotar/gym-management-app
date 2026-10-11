<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BiometricReceiptStatusEnum: string implements HasColor, HasLabel
{
    case CLAIMED = 'claimed';
    case PROCESSED = 'processed';
    case DENIED = 'denied';
    case IGNORED = 'ignored';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::CLAIMED => 'Claimed',
            self::PROCESSED => 'Processed',
            self::DENIED => 'Denied',
            self::IGNORED => 'Ignored',
            self::FAILED => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CLAIMED => 'info',
            self::PROCESSED => 'success',
            self::DENIED => 'danger',
            self::IGNORED => 'gray',
            self::FAILED => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::PROCESSED, self::DENIED, self::IGNORED => true,
            self::CLAIMED, self::FAILED => false,
        };
    }

    public function isProcessed(): bool
    {
        return $this === self::PROCESSED;
    }

    public function isDenied(): bool
    {
        return $this === self::DENIED;
    }

    public function isIgnored(): bool
    {
        return $this === self::IGNORED;
    }
}
