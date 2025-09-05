<?php

namespace App\Filament\Admin\Pages;

use App\Models\Lead;
use App\Enums\LeadStatus;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;

class LeadKanbanBoard extends KanbanBoard
{
    protected static ?string $navigationIcon = 'phosphor-columns';
    protected static ?string $navigationGroup = 'CRM';
    protected static string $model = Lead::class;
    protected static string $statusEnum = LeadStatus::class;

    protected static string $recordTitleAttribute = 'name';

    protected static string $recordStatusAttribute = 'status';
}
