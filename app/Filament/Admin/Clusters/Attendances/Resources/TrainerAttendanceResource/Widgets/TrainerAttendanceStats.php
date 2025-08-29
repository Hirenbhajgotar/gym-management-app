<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Widgets;

use App\Filament\BaseWidgets\BaseAttendanceStats;
use App\Models\Trainer;

class TrainerAttendanceStats extends BaseAttendanceStats
{
    protected function getAttendanceableType(): string
    {
        return Trainer::class;
    }

    protected function getEntityName(): string
    {
        return 'Trainers';
    }

    protected function getEntityModel(): string
    {
        return Trainer::class;
    }
}
