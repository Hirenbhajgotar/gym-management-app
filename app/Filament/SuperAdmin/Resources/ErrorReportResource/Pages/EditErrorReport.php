<?php

namespace App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages;

use App\Filament\SuperAdmin\Resources\ErrorReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditErrorReport extends EditRecord
{
    protected static string $resource = ErrorReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
