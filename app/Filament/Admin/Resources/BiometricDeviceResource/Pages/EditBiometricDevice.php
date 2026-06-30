<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BiometricDeviceResource\Pages;

use App\Filament\Admin\Resources\BiometricDeviceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBiometricDevice extends EditRecord
{
    protected static string $resource = BiometricDeviceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Device updated')
            ->body('The device has been updated successfully.');
    }
}
