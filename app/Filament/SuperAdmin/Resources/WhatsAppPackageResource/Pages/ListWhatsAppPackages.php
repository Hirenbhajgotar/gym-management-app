<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\WhatsAppPackageResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Resources\WhatsAppPackageResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppPackages extends ListRecords
{
    protected static string $resource = WhatsAppPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
