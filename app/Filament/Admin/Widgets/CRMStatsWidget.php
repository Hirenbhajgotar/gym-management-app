<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\DefaultRole;
use App\Enums\LeadStatus;
use App\Filament\BaseWidgets\BaseStatsOverviewWidget;
use App\Models\Lead;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class CRMStatsWidget extends BaseStatsOverviewWidget
{
    protected static ?string $pollingInterval = null;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['widget'],
            DefaultRole::RECEPTIONIST->value => ['widget'],
            DefaultRole::TRAINER->value => [],
        ];
    }

    protected function getListeners(): array
    {
        /** @var \App\Models\Gym|null $tenant */
        $tenant = Filament::getTenant();
        $gymId = $tenant?->id;

        return [
            "echo-private:reports.{$gymId},.LeadStatsUpdated" => '$refresh',
        ];
    }

    protected function getStats(): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return Cache::flexible(Lead::DASHBOARD_CRM_STATS_WIDGET . $gym->id, [now()->addHour(), now()->addHours(24)], function () {
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
