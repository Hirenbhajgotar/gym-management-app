<?php

namespace App\Filament\SuperAdmin\Resources\CountryResource\Pages;

use App\Filament\SuperAdmin\Resources\CountryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCountry extends CreateRecord
{
    protected static string $resource = CountryResource::class;
}
