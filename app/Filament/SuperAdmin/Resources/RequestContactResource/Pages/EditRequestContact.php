<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\RequestContactResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\SuperAdmin\Resources\RequestContactResource;
use Filament\Resources\Pages\EditRecord;

class EditRequestContact extends EditRecord
{
    protected static string $resource = RequestContactResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return RequestContactResource::getUrl('index');
    }
}
