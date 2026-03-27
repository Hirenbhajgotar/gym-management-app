<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BiometricDeviceStatusEnum: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Maintenance => 'Maintenance',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Maintenance => 'warning',
        };
    }
}
