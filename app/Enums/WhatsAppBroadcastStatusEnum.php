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
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::SENDING => 'Sending',
            self::COMPLETED => 'Completed',
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
            self::CANCELLED => 'danger',
            self::FAILED => 'danger',
        };
    }
}
