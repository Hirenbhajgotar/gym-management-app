<?php

namespace App\Filament\Admin\Resources\ReportGoalResource\Pages;

use App\Filament\Admin\Resources\ReportGoalResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateReportGoal extends CreateRecord
{
    protected static string $resource = ReportGoalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        $data['gym_id'] = $gym?->id;
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
