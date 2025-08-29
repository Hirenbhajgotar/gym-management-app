<?php

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

    protected static string $view = 'filament.admin.clusters.permissions.pages.receptionist-permissions-page';

    protected function getRoleName(): string
    {
        return DefaultRole::RECEPTIONIST->value;
    }

    protected function getRoleDisplayName(): string
    {
        return DefaultRole::RECEPTIONIST->getLabel();
    }

    protected function getQuickSetupPermissions(): array
    {
        return [
            'view_gym_member',
            'view_any_gym_member',
            'create_gym_member',
            'edit_gym_member',
            'view_membership_plan',
            'view_any_membership_plan',
            'create_membership_plan',
            'edit_membership_plan',
            'view_payment',
            'view_any_payment',
            'create_payment',
            'edit_payment',
            'widget_stats_overview',
            'widget_recent_members',
            'widget_membership_expiry_alerts_chart',
            'widget_todays_birthdays',
            'access_notifications',
            'manage_member_notes',
            'send_notifications',
            'access_member_communication',
        ];
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
