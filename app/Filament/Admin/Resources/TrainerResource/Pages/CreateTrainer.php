<?php

namespace App\Filament\Admin\Resources\TrainerResource\Pages;

use App\Filament\Admin\Resources\TrainerResource;
use App\Services\AvatarService;
use App\Traits\HasTenantAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainer extends CreateRecord
{
    use HasTenantAccess;

    protected static string $resource = TrainerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['gym_id'] = $this->getGymId();

        // Generate avatar if no profile photo is uploaded
        if (blank($data['profile_photo'])) {
            $data['profile_photo'] = AvatarService::generate($data['name']);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return TrainerResource::getUrl('index');
    }
}
