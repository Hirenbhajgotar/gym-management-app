<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

class ListTrainerAttendances extends ListRecords
{
    protected static string $resource = TrainerAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->color('primary'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return TrainerAttendanceResource::getWidgets();
    }

    public function getTabs(): array
    {
        $resource = static::getResource();

        return [
            'today' => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', Date::today()))
                ->badge($resource::getEloquentQuery()->whereDate('created_at', Date::today())->count()),

            'week' => Tab::make('This Week')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('created_at', [Date::now()->startOfWeek(), Date::now()->endOfWeek()]))
                ->badge($resource::getEloquentQuery()->whereBetween('created_at', [Date::now()->startOfWeek(), Date::now()->endOfWeek()])->count()),

            'month' => Tab::make('This Month')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('created_at', [Date::now()->startOfMonth(), Date::now()->endOfMonth()]))
                ->badge($resource::getEloquentQuery()->whereBetween('created_at', [Date::now()->startOfMonth(), Date::now()->endOfMonth()])->count()),

            'all' => Tab::make('All')
                ->badge($resource::getEloquentQuery()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'today';
    }
}
