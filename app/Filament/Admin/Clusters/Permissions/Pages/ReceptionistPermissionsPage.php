<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Permissions\Pages;

use App\Enums\DefaultRole;
use App\Filament\Admin\Widgets\RolePermissionsStatsWidget;
use App\Filament\BasePages\BasePermissionsPage;

class ReceptionistPermissionsPage extends BasePermissionsPage
{
    protected ?string $heading = 'Receptionist Permissions';

    protected static ?string $title = 'Receptionist';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $slug = 'receptionist';

    protected function getRoleName(): string
    {
        return DefaultRole::RECEPTIONIST->value;
    }

    protected function getRoleDisplayName(): string
    {
        return DefaultRole::RECEPTIONIST->getLabel();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RolePermissionsStatsWidget::make([
                'role' => 'receptionist',
                'roleDisplayName' => 'Receptionist',
            ]),
        ];
    }
}
