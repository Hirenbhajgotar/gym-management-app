<?php

namespace App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages;

use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppPackages extends ListRecords
{
    protected static string $resource = WhatsAppPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
