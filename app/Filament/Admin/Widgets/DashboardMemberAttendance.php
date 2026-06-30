<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Filament\BaseWidgets\BaseAttendanceStats;
use App\Models\GymMember;
use Filament\Facades\Filament;

class DashboardMemberAttendance extends BaseAttendanceStats
{
    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 6;

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.AttendanceStatsUpdated" => '$refresh',
        ];
    }

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
