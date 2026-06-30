<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource\Pages;

use App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBusinessType extends CreateRecord
{
    protected static string $resource = BusinessTypeResource::class;
}
