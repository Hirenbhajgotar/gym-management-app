<?php

declare(strict_types=1);

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

    public function getDescriptiveLabel(): string
    {
        return match ($this) {
            self::Relay => 'Built-in Relay - Device directly controls door lock',
            self::Wiegand => 'Wiegand Output - Device sends signals to external controller',
            self::Api => 'Separate Door API - calls a door controller',
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

    public static function toSelectOptions(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case) => [$case->value => $case->getDescriptiveLabel()]
        )->all();
    }

    public function getShortDescription(): string
    {
        return match ($this) {
            self::Relay => 'FluxFitness sends granted/denied in webhook response. Device triggers relay.',
            self::Wiegand => 'FluxFitness sends granted/denied in webhook response. Device sends Wiegand signal.',
            self::Api => 'FluxFitness will call your door controller API directly on every check-in.',
        };
    }
}
