<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BiometricTypeEnum: string implements HasColor, HasLabel
{
    case Face = 'face';
    case Fingerprint = 'fingerprint';
    case Iris = 'iris';

    public function getLabel(): string
    {
        return match ($this) {
            self::Face => 'Face',
            self::Fingerprint => 'Fingerprint',
            self::Iris => 'Iris',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Face => 'info',
            self::Fingerprint => 'success',
            self::Iris => 'warning',
        };
    }
}
