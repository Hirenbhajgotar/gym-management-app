<?php

namespace App\Filament\Admin\Resources\TrainerResource\Pages;

use App\Filament\Admin\Resources\TrainerResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainer extends CreateRecord
{
    // use HasWizard;

    protected static string $resource = TrainerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['gym_id'] = Filament::getTenant()->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return TrainerResource::getUrl('index');
    }
}
