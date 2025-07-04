<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasIcon, HasColor, HasLabel
{
    case Present = 'Present';
    case Completed = 'Completed';
    case Absent = 'Absent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Completed => 'Completed',
            self::Absent => 'Absent',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Present => 'info',
            self::Completed => 'success',
            self::Absent => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Present => 'heroicon-o-check-circle',
            self::Completed => 'heroicon-o-check',
            self::Absent => 'heroicon-o-x-circle',
        };
    }
}
