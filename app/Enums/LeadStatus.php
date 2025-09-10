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
    case FOLLOW_UP_PENDING = 'follow-up-pending';
    case CONVERTED = 'converted';
    case LOST = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::CONTACTED => 'Contacted',
            self::FOLLOW_UP_PENDING => 'Follow-up Pending',
            self::CONVERTED => 'Converted',
            self::LOST => 'Lost',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NEW => 'primary',
            self::CONTACTED => 'warning',
            self::FOLLOW_UP_PENDING => 'info',
            self::CONVERTED => 'success',
            self::LOST => 'danger',
        };
    }
}
