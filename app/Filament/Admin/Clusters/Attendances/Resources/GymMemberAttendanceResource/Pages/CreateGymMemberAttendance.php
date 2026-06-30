<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGymMemberAttendance extends CreateRecord
{
    protected static string $resource = GymMemberAttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['attendanceable_type'] = GymMemberAttendanceResource::getAttendanceableType();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return GymMemberAttendanceResource::getUrl('index');
    }
}
