<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FollowUpStatus: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case MISSED = 'missed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::MISSED => 'Missed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::COMPLETED => 'success',
            self::MISSED => 'danger',
        };
    }
}
