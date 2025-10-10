<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasColor, HasIcon, HasLabel
{
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case SUSPENDED = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            self::TRIAL => 'Trial',
            self::ACTIVE => 'Active',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TRIAL => 'info',
            self::ACTIVE => 'success',
            self::CANCELLED => 'warning',
            self::EXPIRED => 'danger',
            self::SUSPENDED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::TRIAL => 'heroicon-m-clock',
            self::ACTIVE => 'heroicon-m-check-circle',
            self::CANCELLED => 'heroicon-m-x-circle',
            self::EXPIRED => 'heroicon-m-exclamation-triangle',
            self::SUSPENDED => 'heroicon-m-pause-circle',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::TRIAL, self::ACTIVE]);
    }

    public function canAccessFeatures(): bool
    {
        return $this->isActive();
    }
}
