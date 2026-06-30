<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ErrorPriority: string implements HasColor, HasIcon, HasLabel
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW => 'Low Priority',
            self::MEDIUM => 'Medium Priority',
            self::HIGH => 'High Priority',
            self::CRITICAL => 'Critical Priority',
        };
    }

    public function getDescriptiveLabel(): string
    {
        return match ($this) {
            self::LOW => 'Low - Minor inconvenience',
            self::MEDIUM => 'Medium - Affects workflow',
            self::HIGH => 'High - Blocks important tasks',
            self::CRITICAL => 'Critical - System unusual',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::LOW => 'gray',
            self::MEDIUM => 'warning',
            self::HIGH => 'orange',
            self::CRITICAL => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::LOW => 'heroicon-o-exclamation-circle',
            self::MEDIUM => 'heroicon-o-exclamation-triangle',
            self::HIGH => 'heroicon-o-shield-exclamation',
            self::CRITICAL => 'heroicon-o-x-circle',
        };
    }
}
