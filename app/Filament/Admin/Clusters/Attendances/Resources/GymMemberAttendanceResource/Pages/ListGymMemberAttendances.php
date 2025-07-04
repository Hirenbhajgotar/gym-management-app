<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGymMemberAttendances extends ListRecords
{
    protected static string $resource = GymMemberAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
