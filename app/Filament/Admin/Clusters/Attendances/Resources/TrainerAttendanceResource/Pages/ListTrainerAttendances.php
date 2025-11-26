<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTrainerAttendances extends ListRecords
{
    protected static string $resource = TrainerAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
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
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', Carbon::today()))
                ->badge($resource::getEloquentQuery()->whereDate('created_at', Carbon::today())->count()),

            'week' => Tab::make('This Week')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]))
                ->badge($resource::getEloquentQuery()->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count()),

            'month' => Tab::make('This Month')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]))
                ->badge($resource::getEloquentQuery()->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])->count()),

            'all' => Tab::make('All')
                ->badge($resource::getEloquentQuery()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'today';
    }
}
