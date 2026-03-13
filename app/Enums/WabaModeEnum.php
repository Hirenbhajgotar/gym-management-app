<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WabaModeEnum: string implements HasColor, HasLabel
{
    case SHARED = 'shared';
    case INDIVIDUAL = 'individual';
    case MANUAL = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::SHARED => 'Shared WABA',
            self::INDIVIDUAL => 'Individual WABA',
            self::MANUAL => 'Manual Connection',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SHARED => 'warning',
            self::INDIVIDUAL => 'success',
            self::MANUAL => 'info',
        };
    }
}
