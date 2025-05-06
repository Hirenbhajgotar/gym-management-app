<?php

namespace App\Filament\Resources\CountryResource\Pages;

use App\Filament\Resources\CountryResource;
use App\Models\Country;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCountries extends ListRecords
{
    protected static string $resource = CountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')->badge(Country::query()->count()),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->active())
                ->badge(Country::query()->active()->count()),
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->inActive())
                ->badge(Country::query()->inactive()->count()),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'active';
    }
}
