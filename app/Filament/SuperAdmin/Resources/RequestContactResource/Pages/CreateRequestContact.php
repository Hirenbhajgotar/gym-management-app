<?php

namespace App\Filament\SuperAdmin\Resources\RequestContactResource\Pages;

use App\Filament\SuperAdmin\Resources\RequestContactResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRequestContact extends CreateRecord
{
    protected static string $resource = RequestContactResource::class;

    protected function getRedirectUrl(): string
    {
        return RequestContactResource::getUrl('index');
    }
}
