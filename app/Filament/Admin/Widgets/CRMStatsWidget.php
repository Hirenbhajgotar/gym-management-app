<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class CRMStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return Cache::flexible(Lead::DASHBOARD_CRM_STATS_WIDGET . Filament::getTenant()->id, [10, 20], function () {
            return [
                Stat::make('New Leads', Lead::where('status', 'new')->count())
                    ->description('Total new leads')
                    ->color('success'),
                Stat::make('Leads in Pipeline', Lead::where('status', '!=', LeadStatus::CONVERTED)->where('status', '!=', LeadStatus::LOST)->count())
                    ->description('Active leads')
                    ->color('warning'),
                Stat::make('Closed Won Leads', Lead::where('status', LeadStatus::CONVERTED)->whereMonth('updated_at', now()->month)->count())
                    ->description('This month')
                    ->color('primary'),
            ];
        });
    }
}
