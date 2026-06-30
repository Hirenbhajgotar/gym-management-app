<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppBroadcastRecipientStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case SENDING = 'sending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SENDING => 'Sending',
            self::SENT => 'Sent',
            self::DELIVERED => 'Delivered',
            self::READ => 'Read',
            self::FAILED => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::SENDING => 'blue',
            self::SENT => 'green',
            self::DELIVERED => 'green',
            self::READ => 'green',
            self::FAILED => 'red',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::SENDING => 'heroicon-o-paper-airplane',
            self::SENT => 'heroicon-o-paper-airplane',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::READ => 'heroicon-o-eye',
            self::FAILED => 'heroicon-o-x-circle',
        };
    }
}
