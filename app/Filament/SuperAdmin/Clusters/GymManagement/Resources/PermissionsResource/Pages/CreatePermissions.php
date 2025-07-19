<?php

namespace App\Filament\SuperAdmin\Clusters\GymManagement\Resources\PermissionsResource\Pages;

use App\Filament\SuperAdmin\Clusters\GymManagement\Resources\PermissionsResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePermissions extends CreateRecord
{
    protected static string $resource = PermissionsResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Permission created')
            ->body('The permission has been created successfully.');
    }
}
