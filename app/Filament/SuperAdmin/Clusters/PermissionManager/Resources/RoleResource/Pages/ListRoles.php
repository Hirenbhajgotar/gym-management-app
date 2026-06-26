<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\RoleResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\RoleResource;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
