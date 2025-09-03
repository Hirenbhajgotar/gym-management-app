<?php

namespace App\Enums;

use Mokhosh\FilamentKanban\Concerns\IsKanbanStatus;

enum DealStage: string
{
    use IsKanbanStatus;

    case PROSPECTING = 'prospecting';
    case PROPOSAL = 'proposal';
    case NEGOTIATION = 'negotiation';
    case CLOSED_WON = 'closed_won';
    case CLOSED_LOST = 'closed_lost';
}
