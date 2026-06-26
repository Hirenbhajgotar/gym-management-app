<?php

namespace App\Filament\SuperAdmin\Resources\PlanResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Resources\PlanResource;
use Filament\Resources\Pages\ListRecords;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
