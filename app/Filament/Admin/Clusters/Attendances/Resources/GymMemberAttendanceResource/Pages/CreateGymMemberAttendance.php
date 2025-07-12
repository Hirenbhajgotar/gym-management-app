<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource;
use App\Models\GymMember;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateGymMemberAttendance extends CreateRecord
{
    protected static string $resource = GymMemberAttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['attendanceable_type'] = static::$resource::getAttendanceableType();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return GymMemberAttendanceResource::getUrl('index');
    }
}
