<?php

namespace App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource\Pages;

use App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBusinessType extends CreateRecord
{
    protected static string $resource = BusinessTypeResource::class;
}
