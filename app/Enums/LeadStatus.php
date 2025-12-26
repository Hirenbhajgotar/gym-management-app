<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Mokhosh\FilamentKanban\Concerns\IsKanbanStatus;

enum LeadStatus: string implements HasColor, HasLabel
{
    use IsKanbanStatus;

    case NEW = 'new';
    case CONTACTED = 'contacted';
    case CONVERTED = 'converted';
    case LOST = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::CONTACTED => 'Contacted',
            self::CONVERTED => 'Converted',
            self::LOST => 'Lost',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NEW => 'primary',
            self::CONTACTED => 'warning',
            self::CONVERTED => 'success',
            self::LOST => 'danger',
        };
    }
}
