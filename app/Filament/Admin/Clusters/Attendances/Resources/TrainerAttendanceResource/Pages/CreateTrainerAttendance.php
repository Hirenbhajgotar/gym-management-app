<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainerAttendance extends CreateRecord
{
    protected static string $resource = TrainerAttendanceResource::class;

    protected function getRedirectUrl(): string
    {
        return TrainerAttendanceResource::getUrl('index');
    }
}
