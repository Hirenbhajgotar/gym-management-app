<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeadSource: string implements HasLabel
{
    case WEBSITE = 'website';
    case WALK_IN = 'walk_in';
    case REFERRAL = 'referral';
    case CALL = 'call';
    case SOCIAL_MEDIA = 'social_media';
    case FACEBOOK = 'facebook';
    case INSTAGRAM = 'instagram';
    case WHATSAPP = 'whatsapp';
    case JUSTDIAL = 'justdial';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::WEBSITE => 'Website',
            self::WALK_IN => 'Walk-in',
            self::REFERRAL => 'Referral',
            self::CALL => 'Call',
            self::SOCIAL_MEDIA => 'Social Media',
            self::FACEBOOK => 'Facebook',
            self::INSTAGRAM => 'Instagram',
            self::WHATSAPP => 'WhatsApp',
            self::JUSTDIAL => 'JustDial',
            self::OTHER => 'Other',
        };
    }
}
