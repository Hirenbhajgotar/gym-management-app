<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Widgets;

use App\Enums\AttendanceStatus;
use App\Filament\BaseWidgets\BaseAttendanceStats as BaseWidgetsBaseAttendanceStats;
use App\Models\Attendances;
use App\Models\GymMember;
use BaseAttendanceStats;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MemberAttendanceStats extends BaseWidgetsBaseAttendanceStats
{
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
