<?php

namespace App\Filament\SuperAdmin\Resources\StateResource\Pages;

use App\Filament\SuperAdmin\Resources\StateResource;
use App\Models\State;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStates extends ListRecords
{
    protected static string $resource = StateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        /**
         * @var \Illuminate\Database\Eloquent\Builder $counts
         */
        $counts = State::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN states.status = 1 THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN states.status = 0 THEN 1 ELSE 0 END) as inactive_count
            ')
            ->join('countries', function ($join) {
                $join->on('states.country_id', '=', 'countries.id')
                    ->where('countries.status', 1);
            })
            ->first();

        return [
            'all' => Tab::make('All')->badge($counts->total)
                ->icon('heroicon-o-globe-alt'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('states.status', 1))
                ->badge($counts->active_count)
                ->icon('heroicon-o-check-circle'),
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('states.status', 0))
                ->badge($counts->inactive_count)
                ->icon('heroicon-o-x-circle'),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'active';
    }
}
