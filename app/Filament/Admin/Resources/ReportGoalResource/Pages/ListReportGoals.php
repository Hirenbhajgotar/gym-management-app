<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ReportGoalResource\Pages;

use App\Filament\Admin\Resources\ReportGoalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReportGoals extends ListRecords
{
    protected static string $resource = ReportGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
