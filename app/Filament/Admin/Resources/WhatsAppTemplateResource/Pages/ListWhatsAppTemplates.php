<?php

namespace App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages;

use App\Filament\Admin\Resources\WhatsAppTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppTemplates extends ListRecords
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Template')
                ->icon('heroicon-o-plus'),
        ];
    }
}
