<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BiometricDoorControlType: string implements HasColor, HasLabel
{
    case Relay = 'relay';
    case Wiegand = 'wiegand';
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::Relay => 'Built-in Relay',
            self::Wiegand => 'Wiegand Output',
            self::Api => 'Separate Door API',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Relay => 'success',
            self::Wiegand => 'blue',
            self::Api => 'purple',
        };
    }
}
