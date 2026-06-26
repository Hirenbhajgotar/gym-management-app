<?php

namespace App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource;
use Filament\Resources\Pages\ListRecords;

class ListScheduledReports extends ListRecords
{
    protected static string $resource = ScheduledReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
