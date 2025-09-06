<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Currency;
use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\MembershipPlan;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;

class DealsKanbanBoard extends KanbanBoard
{
    protected static string $model = Lead::class;

    protected static string $statusEnum = DealStage::class;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Deals Kanban';

    protected static string $recordTitleAttribute = 'title';

    protected static string $recordStatusAttribute = 'stage';

    protected static ?string $navigationGroup = 'CRM';

    // protected static ?int $navigationSort = 2;

    protected function records(): Collection
    {
        return Deal::with(['lead', 'membershipPlan'])->get();
    }

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

    // protected function additionalRecordData(Model $model): Collection
    // {
    //     return collect([
    //         'lead_name' => $model->lead->name,
    //         'membership_plan' => $model->membershipPlan->name,
    //     ]);
    // }
}
