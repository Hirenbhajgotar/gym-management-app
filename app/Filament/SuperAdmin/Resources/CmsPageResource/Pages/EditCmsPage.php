<?php

namespace App\Filament\SuperAdmin\Resources\CmsPageResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\SuperAdmin\Resources\CmsPageResource;
use Filament\Resources\Pages\EditRecord;

class EditCmsPage extends EditRecord
{
    protected static string $resource = CmsPageResource::class;

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
}
