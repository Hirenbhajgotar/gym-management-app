<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppPhoneNumberTypesEnum: string implements HasColor, HasLabel
{
    case BUSINESS = 'business';
    case LOCAL = 'local';
    case MOBILE = 'mobile';
    case TOLL_FREE = 'toll_free';

    public function getLabel(): string
    {
        return match ($this) {
            self::BUSINESS => 'Business',
            self::LOCAL => 'Local',
            self::MOBILE => 'Mobile',
            self::TOLL_FREE => 'Toll Free',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::BUSINESS => 'success',
            self::LOCAL => 'info',
            self::MOBILE => 'warning',
            self::TOLL_FREE => 'success',
        };
    }
}
