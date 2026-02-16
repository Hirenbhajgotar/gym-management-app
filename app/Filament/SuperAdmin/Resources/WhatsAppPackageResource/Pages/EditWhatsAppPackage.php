<?php

namespace App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages;

use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppPackage extends EditRecord
{
    protected static string $resource = WhatsAppPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
