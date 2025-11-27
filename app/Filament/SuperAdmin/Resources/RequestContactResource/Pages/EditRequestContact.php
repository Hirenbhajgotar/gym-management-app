<?php

namespace App\Filament\SuperAdmin\Resources\RequestContactResource\Pages;

use App\Filament\SuperAdmin\Resources\RequestContactResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRequestContact extends EditRecord
{
    protected static string $resource = RequestContactResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return RequestContactResource::getUrl("index");
    }
}
