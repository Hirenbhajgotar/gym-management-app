<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserStatus: int implements HasLabel, HasColor
{
    case INACTIVE = 0;
    case ACTIVE = 1;
    case UNDER_REVIEW = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::INACTIVE => 'Inactive',
            self::ACTIVE => 'Active',
            self::UNDER_REVIEW => 'Under Review',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::INACTIVE => 'danger',
            self::ACTIVE => 'success',
            self::UNDER_REVIEW => 'warning',
        };
    }
}
