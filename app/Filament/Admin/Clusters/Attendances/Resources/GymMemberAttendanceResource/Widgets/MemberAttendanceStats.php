<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Widgets;

use App\Filament\BaseWidgets\BaseAttendanceStats as BaseWidgetsBaseAttendanceStats;
use App\Models\GymMember;

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
