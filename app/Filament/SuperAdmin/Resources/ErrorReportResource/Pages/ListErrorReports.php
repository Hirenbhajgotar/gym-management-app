<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Resources\ErrorReportResource;
use App\Models\ErrorReport;
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
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All')
                ->badge(Cache::flexible(ErrorReport::ERROR_REPORT_ALL_COUNT_CACHE_KEY, [now()->addHours(6), now()->addHours(24)], fn () => ErrorReport::query()->count())),
        ];

        $statusTabs = [
            'pending' => ErrorReport::ERROR_REPORT_PENDING_COUNT_CACHE_KEY,
            'investigating' => ErrorReport::ERROR_REPORT_INVESTIGATING_COUNT_CACHE_KEY,
            'resolved' => ErrorReport::ERROR_REPORT_RESOLVED_COUNT_CACHE_KEY,
        ];

        foreach ($statusTabs as $status => $cacheKey) {
            $tabs[$status] = Tab::make(ucfirst($status))
                ->modifyQueryUsing(fn (Builder $query) => $query->{$status}())
                ->badge(Cache::flexible($cacheKey, [now()->addHours(6), now()->addHours(24)], fn () => ErrorReport::{$status}()->count()));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }
}
