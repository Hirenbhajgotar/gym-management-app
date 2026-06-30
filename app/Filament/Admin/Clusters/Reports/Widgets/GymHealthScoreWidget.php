<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Filament\BaseWidgets\BaseWidget;
use App\Services\GymHealthScoreService;
use Filament\Facades\Filament;

class GymHealthScoreWidget extends BaseWidget
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
        $service = resolve(GymHealthScoreService::class);

        return $service->calculateHealthScore($gymId);
    }
}
