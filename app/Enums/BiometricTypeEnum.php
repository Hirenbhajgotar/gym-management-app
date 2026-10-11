<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BiometricTypeEnum: string implements HasColor, HasLabel
{
    case AllInOne = 'all_in_one';
    case Face = 'face';
    case Fingerprint = 'fingerprint';
    case Card = 'card';
    case Iris = 'iris';

    public function getLabel(): string
    {
        return match ($this) {
            self::AllInOne => 'All-in-One / Hybrid (Face, Fingerprint, Card)',
            self::Face => 'Face Recognition',
            self::Fingerprint => 'Fingerprint',
            self::Card => 'RFID / NFC Card',
            self::Iris => 'Iris Scanner',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AllInOne => 'primary',
            self::Face => 'info',
            self::Fingerprint => 'success',
            self::Card => 'warning',
            self::Iris => 'gray',
        };
    }
}
