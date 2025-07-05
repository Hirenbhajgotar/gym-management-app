<?php

namespace App\Filament\BaseWidgets;

use App\Enums\AttendanceStatus;
use App\Models\Attendances;
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

        $totalPresent = Attendances::where('attendanceable_type', $attendanceableType)
            ->where('gym_id', $currentGymId)
            ->whereDate('check_in_time', $today)
            ->whereNull('check_out_time')
            ->where('status', AttendanceStatus::Present)
            ->count();

        $totalCompleted = Attendances::where('attendanceable_type', $attendanceableType)
            ->where('gym_id', $currentGymId)
            ->whereDate('check_in_time', $today)
            ->whereNotNull('check_out_time')
            ->where('status', AttendanceStatus::Completed)
            ->count();

        $totalAbsent = $entityModel::where('gym_id', $currentGymId)
            ->whereNotIn('id', Attendances::where('attendanceable_type', $attendanceableType)
                ->where('gym_id', $currentGymId)
                ->whereDate('check_in_time', $today)
                ->pluck('attendanceable_id'))
            ->count();

        return [
            Stat::make('Present Today', $totalPresent)
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
