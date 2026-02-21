<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WabaStatusEnum: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case RESTRICTED = 'restricted';
    case BANNED = 'banned';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::RESTRICTED => 'Restricted',
            self::BANNED => 'Banned',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ACTIVE => 'success',
            self::RESTRICTED => 'danger',
            self::BANNED => 'danger',
        };
    }
}
