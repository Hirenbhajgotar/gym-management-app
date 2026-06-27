<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionsResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionsResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPermissions extends EditRecord
{
    protected static string $resource = PermissionsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Permission updated')
            ->body('The permission has been updated successfully.');
    }
}
