<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainerAttendance extends CreateRecord
{
    protected static string $resource = TrainerAttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // $data['attendanceable_type'] = GymMember::class;
        $data['attendanceable_type'] = static::$resource::getAttendanceableType();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return TrainerAttendanceResource::getUrl('index');
    }
}
