<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages;

use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsAppPackage extends CreateRecord
{
    protected static string $resource = WhatsAppPackageResource::class;

    protected function afterCreate(): void
    {
        Notification::make()
            ->success()
            ->title('Package Created!')
            ->body('Your package has been saved successfully.')
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
