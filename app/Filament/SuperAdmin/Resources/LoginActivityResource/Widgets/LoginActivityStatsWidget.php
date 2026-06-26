<?php

namespace App\Filament\SuperAdmin\Resources\LoginActivityResource\Widgets;

use App\Services\LoginActivityService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LoginActivityStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $service = resolve(LoginActivityService::class);
        $stats = $service->getAggregateStats();

        return [
            Stat::make('Active Logins', $stats['active_sessions'])
                ->icon('phosphor-lightning')
                ->description('Currently logged in')
                ->color('success'),

            Stat::make("Today's Logins", $stats['today_logins'])
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->description('Staff logins today')
                ->color('success'),

            Stat::make("This Week's Logins", $stats['week_logins'])
                ->icon('heroicon-o-calendar')
                ->description('Staff logins this week')
                ->color('primary'),

            Stat::make("This Month's Logins", $stats['month_logins'])
                ->icon('heroicon-o-calendar-days')
                ->description('Staff logins this month')
                ->color('warning'),
        ];
    }
}
