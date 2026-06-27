<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource;
use Filament\Resources\Pages\EditRecord;

class EditScheduledReport extends EditRecord
{
    protected static string $resource = ScheduledReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ScheduledReportResource::getUrl('index'); // Redirect to index page after create
    }
}
