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

    // protected function mutateFormDataBeforeCreate(array $data): array
    // {
    //     $data['default_permissions'] = PermissionSettingResource::extractPermissionsFromFormData($data);
    //     return $data;
    // }
}
