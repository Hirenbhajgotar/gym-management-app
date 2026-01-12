<?php

namespace App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages;

use App\Enums\ErrorStatus;
use App\Filament\SuperAdmin\Resources\ErrorReportResource;
use App\Models\ErrorReport;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ListErrorReports extends ListRecords
{
    protected static string $resource = ErrorReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Cache::flexible(ErrorReport::ERROR_REPORT_ALL_COUNT_CACHE_KEY, [now()->addHours(6), now()->addHours(24)], fn () => ErrorReport::count())),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->pending())
                ->badge(Cache::flexible(ErrorReport::ERROR_REPORT_PENDING_COUNT_CACHE_KEY, [now()->addHours(6), now()->addHours(24)], fn () => ErrorReport::pending()->count())),
            'investigating' => Tab::make('Investigating')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ErrorStatus::INVESTIGATING))
                ->badge(Cache::flexible(ErrorReport::ERROR_REPORT_INVESTIGATING_COUNT_CACHE_KEY, [now()->addHours(6), now()->addHours(24)], fn () => ErrorReport::where('status', ErrorStatus::INVESTIGATING)->count())),
            'resolved' => Tab::make('Resolved')
                ->modifyQueryUsing(fn (Builder $query) => $query->resolved())
                ->badge(Cache::flexible(ErrorReport::ERROR_REPORT_RESOLVED_COUNT_CACHE_KEY, [now()->addHours(6), now()->addHours(24)], fn () => ErrorReport::resolved()->count())),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }
}
