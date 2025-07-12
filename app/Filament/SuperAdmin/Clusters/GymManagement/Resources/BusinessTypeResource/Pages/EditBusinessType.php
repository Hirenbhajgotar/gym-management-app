<?php

namespace App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource\Pages;

use App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBusinessType extends EditRecord
{
    protected static string $resource = BusinessTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
