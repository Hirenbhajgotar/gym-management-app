<?php

namespace App\Filament\Admin\Widgets;

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
            // Stat::make('Deals in Pipeline', Deal::where('stage', '!= ', 'closed_won')->where('stage', '!= ', 'closed_lost')->count())
            //     ->description('Active deals')
            //     ->color('warning'),
            // Stat::make('Closed Won Deals', Deal::where('stage', 'closed_won')->count())
            //     ->description('This month')
            //     ->color('primary'),
        ];
    }
}
