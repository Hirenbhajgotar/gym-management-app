<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\Pages;

use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePermissionSetting extends CreateRecord
{
    protected static string $resource = PermissionSettingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
