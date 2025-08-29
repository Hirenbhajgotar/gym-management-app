<?php

namespace App\Filament\SuperAdmin\Resources\CityResource\Pages;

use App\Filament\SuperAdmin\Resources\CityResource;
use App\Models\City;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCities extends ListRecords
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        // Fetch counts in a single query
        $counts = City::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN cities.status = 1 THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN cities.status = 0 THEN 1 ELSE 0 END) as inactive_count
            ')
            ->join('states', function ($join) {
                $join->on('cities.state_id', '=', 'states.id')
                    ->where('states.status', 1);
            })
            ->first();

        return [
            'all' => Tab::make('All')->badge($counts->total)
                ->icon('heroicon-o-globe-alt'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('cities.status', 1))
                ->badge($counts->active_count)
                ->icon('heroicon-o-check-circle'),
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('cities.status', 0))
                ->badge($counts->inactive_count)
                ->icon('heroicon-o-x-circle'),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'active';
    }
}
