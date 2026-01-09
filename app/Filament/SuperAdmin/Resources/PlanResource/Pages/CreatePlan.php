<?php

namespace App\Filament\SuperAdmin\Resources\PlanResource\Pages;

use App\Filament\SuperAdmin\Resources\PlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function afterCreate(): void
    {
        cache()->forget('all_plans_for_home');
    }
}
