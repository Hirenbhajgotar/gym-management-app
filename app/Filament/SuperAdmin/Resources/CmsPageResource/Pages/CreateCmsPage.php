<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\CmsPageResource\Pages;

use App\Filament\SuperAdmin\Resources\CmsPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCmsPage extends CreateRecord
{
    protected static string $resource = CmsPageResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Cms Page Created';
    }
}
