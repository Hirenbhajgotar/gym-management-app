<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppProviderTypeEnum: string implements HasColor, HasDescription, HasLabel
{
    case WABA = 'waba';
    case TWILIO = 'twilio';

    public function getLabel(): string
    {
        return match ($this) {
            self::TWILIO => 'Twilio',
            self::WABA => 'Meta WABA (WhatsApp Business API)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TWILIO => 'info',
            self::WABA => 'success',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::TWILIO => 'Purchase a number through Twilio',
            self::WABA => 'Connect existing WhatsApp Business',
        };
    }

    public static function toDescriptionsArray(): array
    {
        return [
            self::TWILIO->value => self::TWILIO->getDescription(),
            self::WABA->value => self::WABA->getDescription(),
        ];
    }
}
