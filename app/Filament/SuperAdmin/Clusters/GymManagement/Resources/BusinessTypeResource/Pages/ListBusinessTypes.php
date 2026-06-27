<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListBusinessTypes extends ListRecords
{
    protected static string $resource = BusinessTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
