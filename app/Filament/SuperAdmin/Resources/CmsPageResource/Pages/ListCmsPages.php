<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\CmsPageResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Resources\CmsPageResource;
use Filament\Resources\Pages\ListRecords;

class ListCmsPages extends ListRecords
{
    protected static string $resource = CmsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
