<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionsResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionsResource;
use Filament\Resources\Pages\ListRecords;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
