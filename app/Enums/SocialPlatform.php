<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SocialPlatform: string implements HasColor, HasLabel
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case WhatsApp = 'whatsapp';
    case YouTube = 'youtube';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::WhatsApp => 'WhatsApp Business',
            self::YouTube => 'YouTube',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Facebook => 'blue',
            self::Instagram => 'pink',
            self::WhatsApp => 'green',
            self::YouTube => 'red',
        };
    }
}
