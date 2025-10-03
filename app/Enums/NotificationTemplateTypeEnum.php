<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NotificationTemplateTypeEnum: string implements HasLabel
{
    case JOINING = 'joining';
    case BIRTHDAY = 'birthday';
    case PAYMENT_REMINDER = 'payment_reminder';
    case MEMBERSHIP_EXPIRY = 'membership_expiry';
    case SYSTEM_ERROR = 'system_error';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::JOINING => 'Joining',
            self::BIRTHDAY => 'Birthday',
            self::PAYMENT_REMINDER => 'Payment Reminder',
            self::MEMBERSHIP_EXPIRY => 'Membership Expiry',
        };
    }
}
