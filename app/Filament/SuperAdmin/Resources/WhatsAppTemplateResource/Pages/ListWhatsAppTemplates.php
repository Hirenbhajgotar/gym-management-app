<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppTemplates extends ListRecords
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Template')
                ->icon('heroicon-o-plus'),
        ];
    }
}
