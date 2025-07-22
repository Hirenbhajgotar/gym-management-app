<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\Pages;

use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPermissionSettings extends ListRecords
{
    protected static string $resource = PermissionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
