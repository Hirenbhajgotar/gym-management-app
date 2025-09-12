<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\LeadStatus;
use App\Models\Deal;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CRMStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('New Leads', Lead::where('status', 'new')->count())
                ->description('Total new leads')
                ->color('success'),
            Stat::make('Leads in Pipeline', Lead::where('status', '!=', LeadStatus::CONVERTED)->where('status', '!=', LeadStatus::LOST)->count())
                ->description('Active leads')
                ->color('warning'),
            Stat::make('Closed Won Leads', Lead::where('status', LeadStatus::CONVERTED)->count())
                ->description('This month')
                ->color('primary'),
        ];
    }
}
