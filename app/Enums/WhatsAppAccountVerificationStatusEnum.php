<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppAccountVerificationStatusEnum: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case IN_REVIEW = 'in_review';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_REVIEW => 'In Review',
            self::VERIFIED => 'Verified',
            self::REJECTED => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::IN_REVIEW => 'info',
            self::VERIFIED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
