<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\CountryResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\SuperAdmin\Resources\CountryResource;
use Filament\Resources\Pages\EditRecord;

class EditCountry extends EditRecord
{
    protected static string $resource = CountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
