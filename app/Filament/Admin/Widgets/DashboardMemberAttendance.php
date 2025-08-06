<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\BaseWidgets\BaseAttendanceStats;
use App\Models\GymMember;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardMemberAttendance extends BaseAttendanceStats
{
    protected static ?string $pollingInterval = '900s'; // Refresh every 60 seconds

    protected static ?int $sort = 6;

    protected function getAttendanceableType(): string
    {
        return GymMember::class;
    }

    protected function getEntityName(): string
    {
        return 'Members';
    }

    protected function getEntityModel(): string
    {
        return GymMember::class;
    }
}
