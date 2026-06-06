<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Contracts\HasFilamentPermissions;
use App\Services\GymHealthScoreService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class GymHealthScoreWidget extends Widget implements HasFilamentPermissions
{
    protected static string $view = 'filament.clusters.reports.widgets.gym-health-score-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.RevenueStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.LeadStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.AttendanceStatsUpdated" => '$refresh',
        ];
    }

    public function getHealthData(): array
    {
        $gymId = Filament::getTenant()->id;
        $service = app(GymHealthScoreService::class);

        return $service->calculateHealthScore($gymId);
    }
}
