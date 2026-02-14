<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppPhoneNumberStatusEnum: string implements HasColor, HasLabel
{
    case PURCHASED = 'purchased';
    case ACTIVE = 'active';
    case DEACTIVATED = 'deactivated';

    public function getLabel(): string
    {
        return match ($this) {
            self::PURCHASED => 'Purchased',
            self::ACTIVE => 'Active',
            self::DEACTIVATED => 'Deactivated',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PURCHASED => 'warning',
            self::ACTIVE => 'success',
            self::DEACTIVATED => 'danger',
        };
    }
}
