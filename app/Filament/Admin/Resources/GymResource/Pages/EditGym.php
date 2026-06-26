<?php

namespace App\Filament\Admin\Resources\GymResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Admin\Resources\GymResource;
use Filament\Resources\Pages\EditRecord;

class EditGym extends EditRecord
{
    protected static string $resource = GymResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->successNotification(null), // Disable default success notification,
        ];
    }
}
