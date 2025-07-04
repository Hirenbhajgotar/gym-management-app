<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\Attendances;
use App\Models\GymMember;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MemberAttendanceStats extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $currentGymId = Filament::getTenant()->id;

        $totalPresent = Attendances::where('attendanceable_type', GymMember::class)
            ->where('gym_id', $currentGymId)
            ->whereDate('check_in_time', $today)
            ->whereNull('check_out_time')
            ->where('status', AttendanceStatus::Present)
            ->count();

        $totalCompleted = Attendances::where('attendanceable_type', GymMember::class)
            ->where('gym_id', $currentGymId)
            ->whereDate('check_in_time', $today)
            ->whereNotNull('check_out_time')
            ->where('status', AttendanceStatus::Completed)
            ->count();

        $totalAbsent = GymMember::where('gym_id', $currentGymId)
            ->whereNotIn('id', Attendances::where('attendanceable_type', GymMember::class)
                ->where('gym_id', $currentGymId)
                ->whereDate('check_in_time', $today)
                ->pluck('attendanceable_id'))
            ->count();

        return [
            Stat::make('Present Today', $totalPresent)
                ->description('Members currently checked in')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('success'),
            Stat::make('Completed Today', $totalCompleted)
                ->description('Members who checked out today')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('primary'),
            Stat::make('Absent Today', $totalAbsent)
                ->description('Members marked absent today')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }
}
