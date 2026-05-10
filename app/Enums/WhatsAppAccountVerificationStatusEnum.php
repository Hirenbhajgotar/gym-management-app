<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppAccountVerificationStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case IN_REVIEW = 'in_review';
    case VERIFIED = 'verified';
    case NOT_VERIFIED = 'not_verified';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_REVIEW => 'In Review',
            self::VERIFIED => 'Verified',
            self::NOT_VERIFIED => 'Not Verified',
            self::REJECTED => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::IN_REVIEW => 'info',
            self::VERIFIED => 'success',
            self::NOT_VERIFIED => 'warning',
            self::REJECTED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-pause',
            self::IN_REVIEW => 'heroicon-o-clock',
            self::VERIFIED => 'heroicon-o-check-circle',
            self::NOT_VERIFIED => 'heroicon-o-exclamation-triangle',
            self::REJECTED => 'heroicon-o-x-mark',
        };
    }
}
