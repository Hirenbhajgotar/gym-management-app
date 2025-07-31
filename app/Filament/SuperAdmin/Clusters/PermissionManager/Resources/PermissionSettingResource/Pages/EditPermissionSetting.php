<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\Pages;

use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource;
use App\Models\Permission;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPermissionSetting extends EditRecord
{
    protected static string $resource = PermissionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // protected function mutateFormDataBeforeFill(array $data): array
    // {
    //     // Split default_permissions back into category-specific fields for editing
    //     if (isset($data['default_permissions']) && is_array($data['default_permissions'])) {
    //         $categoryWisePermissions = Permission::whereNotNull('category')
    //             ->where('category', '!=', '')
    //             ->get()
    //             ->groupBy('category');

    //         foreach ($categoryWisePermissions as $category => $permissions) {
    //             $categoryPermissions = $permissions->pluck('name')->toArray();
    //             $selectedPermissions = array_intersect($data['default_permissions'], $categoryPermissions);
    //             $data[$category . '_permissions'] = array_values($selectedPermissions);
    //         }
    //     }
    //     return $data;
    // }

    // protected function mutateFormDataBeforeSave(array $data): array
    // {
    //     $data['default_permissions'] = PermissionSettingResource::extractPermissionsFromFormData($data);
    //     return $data;
    // }
}
