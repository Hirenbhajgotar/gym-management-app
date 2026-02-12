<?php

namespace App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource\Pages;

use App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScheduledReports extends ListRecords
{
    protected static string $resource = ScheduledReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
