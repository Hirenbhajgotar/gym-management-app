<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ErrorStatus: string implements HasLabel, HasColor, HasIcon
{
    case PENDING = 'pending';
    case INVESTIGATING = 'investigating';
    case RESOLVED = 'resolved';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::INVESTIGATING => 'Investigating',
            self::RESOLVED => 'Resolved',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::INVESTIGATING => 'info',
            self::RESOLVED => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-pause-circle',
            self::INVESTIGATING => 'heroicon-o-magnifying-glass',
            self::RESOLVED => 'heroicon-o-check-circle',
        };
    }
}
