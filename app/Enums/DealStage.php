<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Mokhosh\FilamentKanban\Concerns\IsKanbanStatus;

enum DealStage: string implements HasLabel
{
    use IsKanbanStatus;

    case PROSPECTING = 'prospecting';
    case PROPOSAL = 'proposal';
    case NEGOTIATION = 'negotiation';
    case CLOSED_WON = 'closed_won';
    case CLOSED_LOST = 'closed_lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::PROSPECTING => 'Prospecting',
            self::PROPOSAL => 'Proposal',
            self::NEGOTIATION => 'Negotiation',
            self::CLOSED_WON => 'Closed Won',
            self::CLOSED_LOST => 'Closed Lost',
        };
    }

    public function getTitle(): string
    {
        return __($this->getLabel());
    }
}
