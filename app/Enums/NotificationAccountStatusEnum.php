<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NotificationAccountStatusEnum: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case CREATED = 'created';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CREATED => 'Created',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::CREATED => 'blue',
            self::ACTIVE => 'success',
            self::SUSPENDED => 'danger',
        };
    }
}
