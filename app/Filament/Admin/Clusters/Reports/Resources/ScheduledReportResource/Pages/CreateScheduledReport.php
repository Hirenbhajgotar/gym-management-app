<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource\Pages;

use App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateScheduledReport extends CreateRecord
{
    protected static string $resource = ScheduledReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['gym_id'] = Filament::getTenant()->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ScheduledReportResource::getUrl('index'); // Redirect to index page after create
    }
}
