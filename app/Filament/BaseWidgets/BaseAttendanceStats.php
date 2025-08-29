<?php

namespace App\Filament\BaseWidgets;

use App\Services\AttendanceService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

abstract class BaseAttendanceStats extends BaseWidget
{
    abstract protected function getAttendanceableType(): string;

    abstract protected function getEntityName(): string;

    abstract protected function getEntityModel(): string;

    protected function getStats(): array
    {
        $today = Carbon::today();
        $currentGymId = Filament::getTenant()->id;
        $attendanceableType = $this->getAttendanceableType();
        $entityName = $this->getEntityName();
        $entityModel = $this->getEntityModel();

        $todayStats = AttendanceService::getUserTypeStatsForToday($currentGymId, $attendanceableType, $entityModel);

        $totalPresent = $todayStats['active_count'] ?? 0;

        $totalCompleted = $todayStats['completed_count'] ?? 0;

        $totalAbsent = $todayStats['absent_count'] ?? 0;

        return [
            Stat::make('Active Today', $totalPresent)
                ->description("{$entityName} currently checked in")
                ->descriptionIcon('heroicon-o-user-group')
                ->color('success'),
            Stat::make('Completed Today', $totalCompleted)
                ->description("{$entityName} who checked out today")
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('primary'),
            Stat::make('Absent Today', $totalAbsent)
                ->description("{$entityName} marked absent today")
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }
}
