<?php

namespace App\Filament\Admin\Resources\ReportGoalResource\Pages;

use App\Filament\Admin\Resources\ReportGoalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReportGoal extends EditRecord
{
    protected static string $resource = ReportGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
