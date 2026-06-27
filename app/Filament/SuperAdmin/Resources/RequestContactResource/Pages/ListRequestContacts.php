<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\RequestContactResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Resources\RequestContactResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRequestContacts extends ListRecords
{
    protected static string $resource = RequestContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'uncontacted' => Tab::make('Uncontacted')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_contacted', false))
                ->badge(fn () => static::getResource()::getModel()::where('is_contacted', false)->count())
                ->badgeColor('warning'),
            'contacted' => Tab::make('Contacted')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_contacted', true))
                ->badge(fn () => static::getResource()::getModel()::where('is_contacted', true)->count())
                ->badgeColor('success'),
            'recent' => Tab::make('Recent (30 days)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)))
                ->badge(fn () => static::getResource()::getModel()::where('created_at', '>=', now()->subDays(30))->count()),
        ];
    }
}
