<?php

namespace App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppPackage extends EditRecord
{
    protected static string $resource = WhatsAppPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
            ->title('Package Updated')
            ->body('The WhatsApp package has been updated successfully.');
    }
}
