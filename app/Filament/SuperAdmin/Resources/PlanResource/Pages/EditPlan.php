<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\PlanResource\Pages;

use App\Filament\SuperAdmin\Resources\PlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return PlanResource::getUrl('index');
    }
}
