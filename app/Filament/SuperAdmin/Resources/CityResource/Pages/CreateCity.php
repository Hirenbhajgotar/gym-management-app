<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\CityResource\Pages;

use App\Filament\SuperAdmin\Resources\CityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCity extends CreateRecord
{
    protected static string $resource = CityResource::class;
}
