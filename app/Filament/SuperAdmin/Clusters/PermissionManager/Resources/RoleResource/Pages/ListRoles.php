<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\RoleResource\Pages;

use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
