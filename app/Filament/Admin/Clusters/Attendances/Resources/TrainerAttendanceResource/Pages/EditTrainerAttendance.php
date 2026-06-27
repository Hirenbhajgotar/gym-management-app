<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource;
use Filament\Resources\Pages\EditRecord;

class EditTrainerAttendance extends EditRecord
{
    protected static string $resource = TrainerAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return TrainerAttendanceResource::getUrl('index');
    }
}
