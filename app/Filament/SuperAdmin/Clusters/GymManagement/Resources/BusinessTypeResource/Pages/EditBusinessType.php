<?php

namespace App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditBusinessType extends EditRecord
{
    protected static string $resource = BusinessTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
