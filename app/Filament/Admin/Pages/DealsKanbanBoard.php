<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Currency;
use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\MembershipPlan;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;
use Filament\Forms;
use Illuminate\Support\Collection;

class DealsKanbanBoard extends KanbanBoard
{
    protected static string $model = Deal::class;

    protected static string $statusEnum = DealStage::class;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Deals Kanban';

    protected static string $recordTitleAttribute = 'title';

    protected static string $recordStatusAttribute = 'stage';

    protected static ?string $navigationGroup = 'CRM';

    // protected static ?int $navigationSort = 2;

    public function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Deal')
                ->icon('heroicon-o-plus-circle')
                ->model(Deal::class)
                ->form([
                    Forms\Components\Hidden::make('gym_id')
                        ->default(Filament::getTenant()->id),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('lead_id')
                            ->label('Lead')
                            ->options(Lead::all()->pluck('name', 'id'))
                            ->required(),

                        Forms\Components\Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->options(MembershipPlan::all()->pluck('name', 'id'))
                            ->required(),

                        Forms\Components\TextInput::make('value')
                            ->label('Deal Value')
                            ->required()
                            ->numeric()
                            ->gt(0)
                            ->prefix(Currency::INR->getSymbol()),

                        Forms\Components\DatePicker::make('expected_close_date')
                            ->label('Expected Close Date'),

                    ]),
                ]),
        ];
    }
}
