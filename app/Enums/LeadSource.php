<?php

namespace App\Enums;

enum LeadSource: string
{
    case WEBSITE = 'website';
    case WALK_IN = 'walk_in';
    case REFERRAL = 'referral';
    case SOCIAL_MEDIA = 'social_media';
    case OTHER = 'other';
}
