<?php

namespace App\Filament\Admin\Resources\WhatsAppUsageLogResource\Pages;

use App\Filament\Admin\Resources\WhatsAppUsageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppUsageLogs extends ListRecords
{
    protected static string $resource = WhatsAppUsageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
