<?php

namespace App\Filament\SuperAdmin\Resources\CmsPageResource\Pages;

use App\Filament\SuperAdmin\Resources\CmsPageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCmsPages extends ListRecords
{
    protected static string $resource = CmsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
