<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SocialPlatform: string implements HasLabel, HasColor
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case WhatsApp = 'whatsapp';
    case IndiaMart = 'indiamart';
    case JustDial = 'justdial';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::WhatsApp => 'WhatsApp Business',
            self::IndiaMart => 'IndiaMart',
            self::JustDial => 'JustDial',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Facebook => 'blue',
            self::Instagram => 'pink',
            self::WhatsApp => 'green',
            self::IndiaMart => 'orange',
            self::JustDial => 'purple',
        };
    }
}
