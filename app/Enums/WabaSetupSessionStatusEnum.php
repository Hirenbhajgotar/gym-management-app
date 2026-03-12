<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WabaSetupSessionStatusEnum: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::EXPIRED => 'Expired',
            self::FAILED => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::COMPLETED => 'success',
            self::EXPIRED => 'gray',
            self::FAILED => 'danger',
        };
    }
}
