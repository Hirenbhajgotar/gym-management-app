<?php

namespace App\Filament\Pages;

use App\Enums\DealStage;
use App\Models\Deal;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;

class DealsKanbanBoard extends KanbanBoard
{
    protected static string $model = Deal::class;

    protected static string $statusEnum = DealStage::class;

    // protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Deals Kanban';

    protected static string $recordTitleAttribute = 'title';

    protected static ?string $navigationGroup = 'CRM';

    // protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Deals Kanban';
    }
}
