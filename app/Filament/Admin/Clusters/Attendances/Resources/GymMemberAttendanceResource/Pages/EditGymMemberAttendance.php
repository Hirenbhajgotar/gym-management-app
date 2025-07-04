<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGymMemberAttendance extends EditRecord
{
    protected static string $resource = GymMemberAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return GymMemberAttendanceResource::getUrl('index');
    }
}
