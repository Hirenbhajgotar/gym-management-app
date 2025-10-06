<?php

namespace App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages;

use App\Filament\SuperAdmin\Resources\ErrorReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListErrorReports extends ListRecords
{
    protected static string $resource = ErrorReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
