<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\BaseWidgets\BaseAttendanceStats;
use App\Models\GymMember;

class DashboardMemberAttendance extends BaseAttendanceStats
{
    protected static ?string $pollingInterval = null;

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
