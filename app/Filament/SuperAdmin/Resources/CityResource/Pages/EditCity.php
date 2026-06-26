<?php

namespace App\Filament\SuperAdmin\Resources\CityResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\SuperAdmin\Resources\CityResource;
use Filament\Resources\Pages\EditRecord;

class EditCity extends EditRecord
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
