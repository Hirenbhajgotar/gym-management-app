<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrainerAttendances extends ListRecords
{
    protected static string $resource = TrainerAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
