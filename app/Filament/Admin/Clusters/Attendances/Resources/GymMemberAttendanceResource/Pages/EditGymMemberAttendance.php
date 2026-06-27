<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource;
use Filament\Resources\Pages\EditRecord;

class EditGymMemberAttendance extends EditRecord
{
    protected static string $resource = GymMemberAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return GymMemberAttendanceResource::getUrl('index');
    }
}
