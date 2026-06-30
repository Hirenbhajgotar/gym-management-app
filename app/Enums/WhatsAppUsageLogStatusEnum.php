<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppUsageLogStatusEnum: string implements HasColor, HasLabel
{
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case UNDELIVERED = 'undelivered';

    public function getLabel(): string
    {
        return match ($this) {
            self::QUEUED => 'Queued',
            self::SENDING => 'Sending',
            self::SENT => 'Sent',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed',
            self::UNDELIVERED => 'Undelivered',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::QUEUED => 'gray',
            self::SENDING => 'blue',
            self::SENT => 'green',
            self::DELIVERED => 'green',
            self::FAILED => 'red',
            self::UNDELIVERED => 'red',
        };
    }
}
