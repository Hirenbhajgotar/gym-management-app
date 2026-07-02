<?php

declare(strict_types=1);

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

    case PARTIAL = 'partial';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::EXPIRED => 'Expired',
            self::PARTIAL => 'Partial',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::EXPIRED => 'danger',
            self::PARTIAL => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-m-arrow-path',
            self::COMPLETED => 'heroicon-m-check-badge',
            self::FAILED => 'heroicon-m-x-circle',
            self::EXPIRED => 'heroicon-m-clock',
            self::PARTIAL => 'heroicon-m-adjustments-horizontal',
        };
    }

    /**
     * Get options array for Filament select components
     *
     * @return array<string, string>
     */
    public static function toSelectOptions(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case) => [$case->value => $case->getLabel()]
        )->all();
    }
}
