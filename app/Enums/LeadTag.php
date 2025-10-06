<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeadTag: string implements HasColor, HasLabel
{
    case HIGH_PRIORITY = 'High Priority';
    case REFERRAL = 'Referral';
    case FOLLOW_UP = 'Follow Up';
    case TRIAL_SIGNUP = 'Trial Signup';
    case MEMBERSHIP_INQUIRY = 'Membership Inquiry';
    case PRICE_SENSITIVE = 'Price Sensitive';
    case HOT_LEAD = 'Hot Lead';
    case COLD_LEAD = 'Cold Lead';
    case NEW_LEAD = 'New Lead';
    case FOLLOW_UP_SCHEDULED = 'Follow Up Scheduled';
    case INTERESTED_NEEDS_TIME = 'Needs Time';
    case NO_RESPONSE = 'No Response';
    case NOT_INTERESTED = 'Not Interested';

    public function getLabel(): string
    {
        return match ($this) {
            self::HIGH_PRIORITY => 'High Priority',
            self::REFERRAL => 'Referral',
            self::FOLLOW_UP => 'Follow Up',
            self::TRIAL_SIGNUP => 'Trial Signup',
            self::MEMBERSHIP_INQUIRY => 'Membership Inquiry',
            self::PRICE_SENSITIVE => 'Price Sensitive',
            self::HOT_LEAD => 'Hot Lead',
            self::COLD_LEAD => 'Cold Lead',
            self::NEW_LEAD => 'New Lead',
            self::FOLLOW_UP_SCHEDULED => 'Follow Up Scheduled',
            self::INTERESTED_NEEDS_TIME => 'Needs Time',
            self::NO_RESPONSE => 'No Response',
            self::NOT_INTERESTED => 'Not Interested',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::HIGH_PRIORITY => 'danger',
            self::REFERRAL => 'success',
            self::FOLLOW_UP => 'info',
            self::TRIAL_SIGNUP => 'warning',
            self::MEMBERSHIP_INQUIRY => 'primary',
            self::PRICE_SENSITIVE => 'gray',
            self::HOT_LEAD => 'success',
            self::COLD_LEAD => 'danger',
            self::NEW_LEAD => 'info',
            self::FOLLOW_UP_SCHEDULED => 'warning',
            self::INTERESTED_NEEDS_TIME => 'primary',
            self::NO_RESPONSE => 'gray',
            self::NOT_INTERESTED => 'danger',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::HIGH_PRIORITY => 'High Priority',
            self::REFERRAL => 'Referral',
            self::FOLLOW_UP => 'Follow Up',
            self::TRIAL_SIGNUP => 'Trial Signup',
            self::MEMBERSHIP_INQUIRY => 'Membership Inquiry',
            self::PRICE_SENSITIVE => 'Price Sensitive',
            self::HOT_LEAD => 'Ready to convert soon',
            self::COLD_LEAD => 'Not showing much interest',
            self::NEW_LEAD => 'New Lead',
            self::FOLLOW_UP_SCHEDULED => 'Next call/meeting pending',
            self::INTERESTED_NEEDS_TIME => 'Needs more time to decide',
            self::NO_RESPONSE => 'No Response',
            self::NOT_INTERESTED => 'Not Interested',
        };
    }
}
