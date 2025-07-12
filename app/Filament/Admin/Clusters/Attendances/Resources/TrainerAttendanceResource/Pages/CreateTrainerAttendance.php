<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource;
use App\Models\Trainer;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainerAttendance extends CreateRecord
{
    protected static string $resource = TrainerAttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['attendanceable_type'] = Trainer::class;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return TrainerAttendanceResource::getUrl('index');
    }
}
