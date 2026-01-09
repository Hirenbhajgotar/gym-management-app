<?php

namespace App\Filament\SuperAdmin\Resources\PlanResource\Pages;

use App\Filament\SuperAdmin\Resources\PlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->after(function () {
                cache()->forget('all_plans_for_home');
            }),
        ];
    }

    protected function afterSave(): void
    {
        cache()->forget('all_plans_for_home');
    }

    protected function getRedirectUrl(): string
    {
        return PlanResource::getUrl("index");
    }
}
