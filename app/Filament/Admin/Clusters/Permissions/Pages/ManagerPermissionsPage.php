<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Permissions\Pages;

use App\Enums\DefaultRole;
use App\Filament\Admin\Widgets\RolePermissionsStatsWidget;
use App\Filament\BasePages\BasePermissionsPage;

class ManagerPermissionsPage extends BasePermissionsPage
{
    protected ?string $heading = 'Manager Permissions';

    protected static ?string $title = 'Manager';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $slug = 'manager';

    protected function getRoleName(): string
    {
        return DefaultRole::MANAGER->value;
    }

    protected function getRoleDisplayName(): string
    {
        return DefaultRole::MANAGER->getLabel();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RolePermissionsStatsWidget::make([
                'role' => 'manager',
                'roleDisplayName' => 'Manager',
            ]),
        ];
    }
}
