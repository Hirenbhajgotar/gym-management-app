<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeadSource: string implements HasLabel
{
    case WEBSITE = 'website';
    case WALK_IN = 'walk_in';
    case REFERRAL = 'referral';
    case SOCIAL_MEDIA = 'social_media';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::WEBSITE => 'Website',
            self::WALK_IN => 'Walk-in',
            self::REFERRAL => 'Referral',
            self::SOCIAL_MEDIA => 'Social Media',
            self::OTHER => 'Other',
        };
    }
}
