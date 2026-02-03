<?php

namespace App\Filament\Admin\Resources\ReportGoalResource\Pages;

use App\Filament\Admin\Resources\ReportGoalResource;
use Filament\Resources\Pages\ListRecords;

class ListReportGoals extends ListRecords
{
    protected static string $resource = ReportGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
