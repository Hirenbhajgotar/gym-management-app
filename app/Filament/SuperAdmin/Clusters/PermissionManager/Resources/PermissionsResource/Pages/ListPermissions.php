<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionsResource\Pages;

use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
