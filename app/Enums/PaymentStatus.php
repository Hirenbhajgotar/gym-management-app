<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';

    case COMPLETED = 'completed';

    case FAILED = 'failed';

    case EXPIRED = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::EXPIRED => 'Expired',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::EXPIRED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-m-arrow-path',
            self::COMPLETED => 'heroicon-m-check-badge',
            self::FAILED => 'heroicon-m-x-circle',
            self::EXPIRED => 'heroicon-m-clock',
        };
    }

    /**
     * Get options array for Filament select components
     */
    public static function toSelectOptions(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case) => [$case->value => $case->getLabel()]
        )->toArray();
    }
}
