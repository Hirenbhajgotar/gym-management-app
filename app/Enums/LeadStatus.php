<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Mokhosh\FilamentKanban\Concerns\IsKanbanStatus;

enum LeadStatus: string implements HasLabel, HasColor
{
    use IsKanbanStatus;

    case NEW = 'new';
    case CONTACTED = 'contacted';
    case QUALIFIED = 'qualified';
    case UNQUALIFIED = 'unqualified';
    case CONVERTED = 'converted';

    public function getLabel(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::CONTACTED => 'Contacted',
            self::QUALIFIED => 'Qualified',
            self::UNQUALIFIED => 'Unqualified',
            self::CONVERTED => 'Converted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NEW => 'primary',
            self::CONTACTED => 'warning',
            self::QUALIFIED => 'success',
            self::UNQUALIFIED => 'danger',
            self::CONVERTED => 'info',
        };
    }
}
