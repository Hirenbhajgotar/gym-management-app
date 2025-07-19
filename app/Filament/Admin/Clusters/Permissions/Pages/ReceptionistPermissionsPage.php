<?php

namespace App\Filament\Admin\Clusters\Permissions\Pages;

use App\Enums\PermissionCategory;
use App\Filament\Admin\Clusters\Permissions;
use App\Filament\Admin\Widgets\RolePermissionsStatsWidget;
use App\Helpers\NotificationHelper;
use App\Models\User;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use ValueError;

class ReceptionistPermissionsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected ?string $heading = 'Receptionist Permissions';

    protected static ?string $title = 'Receptionist';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $slug = 'receptionist';

    protected static string $view = 'filament.admin.clusters.permissions.pages.receptionist-permissions-page';

    protected static ?string $cluster = Permissions::class;

    public ?array $data = [];

    public function mount(): void
    {
        $this->loadReceptionistPermissions();
    }

    public function form(Form $form): Form
    {
        $permissionsByCategory = $this->getPermissionsByCategory();

        return $form
            ->schema([
                Tabs::make('Permissions')
                    ->tabs([
                        Tabs\Tab::make('Resources')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema($this->buildCategoryTabs($permissionsByCategory, [
                                'attendance',
                                'gym_member',
                                'membership_plan',
                                'receptionist',
                                'trainer',
                            ])),

                        Tabs\Tab::make('Widgets')
                            ->icon('heroicon-o-chart-bar')
                            ->schema($this->buildCategoryTabs($permissionsByCategory, [
                                'widgets',
                            ])),

                        Tabs\Tab::make('Reports')
                            ->icon('heroicon-o-document-chart-bar')
                            ->schema($this->buildCategoryTabs($permissionsByCategory, [
                                'report',
                            ])),

                        Tabs\Tab::make('Other')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema($this->buildCategoryTabs($permissionsByCategory, [
                                'communication',
                                'setting',
                                'general',
                            ])),
                    ]),
            ])
            ->statePath('data');
    }

    private function loadReceptionistPermissions(): void
    {
        $currentTenant = Filament::getTenant();

        // Get the receptionist role for the current gym
        $role = Role::where('name', 'receptionist')
            ->where('gym_id', $currentTenant->id)
            ->first();

        if (!$role) {
            // If role doesn't exist for this gym, create it
            $role = Role::create([
                'name' => 'receptionist',
                'guard_name' => 'web',
                'gym_id' => $currentTenant->id,
            ]);
        }

        $rolePermissions = $role->permissions->pluck('name')->toArray();

        $memberPermissions = array_intersect([
            'view_gym_member',
            'view_any_gym_member',
            'create_gym_member',
            'edit_gym_member',
            'delete_gym_member',
            'restore_gym_member',
            'force_delete_gym_member',
            'replicate_gym_member',
            'reorder_gym_member',
            'export_gym_member',
        ], $rolePermissions);

        $membershipPermissions = array_intersect([
            'view_membership_plan',
            'view_any_membership_plan',
            'create_membership_plan',
            'edit_membership_plan',
            'delete_membership_plan',
            'restore_membership_plan',
            'force_delete_membership_plan',
            'replicate_membership_plan',
            'reorder_membership_plan',
        ], $rolePermissions);

        // $paymentPermissions = array_intersect([
        //     'view_payments',
        //     'create_payments',
        //     'edit_payments'
        // ], $rolePermissions);

        // $classPermissions = array_intersect([
        //     'view_classes',
        //     'book_classes'
        // ], $rolePermissions);

        $reportPermissions = array_intersect([
            'page_reports',
            'view_financial_reports',
            'view_member_reports',
            'view_trainer_reports',
            'view_equipment_reports',
            'export_reports',
        ], $rolePermissions);

        // Widget permissions
        $widgetPermissions = array_intersect([
            'widget_stats_overview',
            'widget_gym_revenue_chart',
            'widget_recent_members',
            'widget_membership_expiry_alerts_chart',
            'widget_todays_birthdays',
            'widget_trainer_performance',
        ], $rolePermissions);

        // System permissions
        $systemPermissions = array_intersect([
            'manage_settings',
            'view_analytics',
            'export_data',
            'import_data',
            'manage_backups',
        ], $rolePermissions);

        // Communication permissions
        $communicationPermissions = array_intersect([
            'send_notifications',
            'send_bulk_messages',
            'manage_announcements',
            'access_member_communication',
            'view_message_history',
        ], $rolePermissions);

        $this->form->fill([
            'gym_member_permissions' => $memberPermissions,
            'membership_plan_permissions' => $membershipPermissions,
            // 'payment_permissions' => $paymentPermissions,
            // 'class_permissions' => $classPermissions,
            'widget_permissions' => $widgetPermissions,
            'report_permissions' => $reportPermissions,
            'system_permissions' => $systemPermissions,
            'communication_permissions' => $communicationPermissions,
        ]);
    }

    public function save(): void
    {
        try {
            DB::beginTransaction();

            $data = $this->form->getState();
            $currentTenant = Filament::getTenant();

            // Get or create the receptionist role for the current gym
            $role = Role::firstOrCreate([
                'name' => 'receptionist',
                'guard_name' => 'web',
                'gym_id' => $currentTenant?->id
            ]);

            // Collect all permissions from the form
            $allPermissions = [];
            foreach ($data as $permissions) {
                if (is_array($permissions)) {
                    $allPermissions = array_merge($allPermissions, $permissions);
                }
            }

            // Get or create team-specific permissions
            $gymPermissions = [];
            foreach ($allPermissions as $permissionName) {
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
                $gymPermissions[] = $permission;
            }

            // dd($allPermissions, $gymPermissions, getPermissionsTeamId());


            // Sync permissions only for this gym's role
            $role->syncPermissions($gymPermissions);

            // Update all receptionist users in this gym
            $this->updateReceptionistUsers($role, $currentTenant);

            DB::commit();

            Notification::make()
                ->title('Receptionist Permissions Updated')
                ->body('Permissions have been updated for the current gym.')
                ->success()
                ->send();
        } catch (Exception $e) {
            DB::rollBack();

            Log::error($e);

            NotificationHelper::unexpectedError('updating permissions', $e)->send();
        }
    }

    private function updateReceptionistUsers(Role $role, $tenant): void
    {
        // Get all users with receptionist role in current gym
        $receptionistUsers = User::whereHas('roles', function ($query) use ($tenant) {
            $query->where('name', 'receptionist')
                ->where('roles.gym_id', $tenant->id);
        })
            ->get();

        foreach ($receptionistUsers as $user) {
            // Sync permissions for this user in this gym context
            $user->syncPermissions($role->permissions, $tenant);
        }
    }

    public static function canAccess(): bool
    {
        // Check if user has gym_owner role for this specific gym
        return auth()->user()->hasRole('gym_owner');
    }

    protected function getActions(): array
    {
        return [
            Action::make('reset_permissions')
                ->label('Reset to Default')
                ->color('gray')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Reset Permissions')
                ->modalDescription('Are you sure you want to reset all receptionist permissions to default values? This action cannot be undone.')
                ->modalSubmitActionLabel('Reset Permissions')
                ->action(function () {
                    $this->resetToDefaultPermissions();
                }),

            Action::make('quick_setup')
                ->label('Quick Setup')
                ->color('info')
                ->icon('heroicon-o-bolt')
                ->action(function () {
                    $this->quickSetupPermissions();
                }),
        ];
    }

    private function resetToDefaultPermissions(): void
    {
        try {
            $currentTenant = Filament::getTenant();

            // Define default permissions for receptionist
            $defaultPermissions = [
                'view_gym_member',
                'create_gym_member',
                'edit_gym_member',
                'view_membership_plan',
                'create_membership_plan',
                'edit_membership_plan',
                // 'view_payment',
                // 'create_payment',
                // 'edit_payment',
                // 'view_class',
                // 'book_class',
                // 'manage_class_bookings',
                'view_member_stats_widget',
                'view_recent_members_widget',
                'view_upcoming_classes_widget',
                'access_notifications',
                'manage_member_notes',
            ];

            DB::beginTransaction();

            $role = Role::firstOrCreate([
                'name' => 'receptionist',
                'guard_name' => 'web',
                'gym_id' => $currentTenant->id,
            ]);

            // Create default permissions
            $gymPermissions = [];
            foreach ($defaultPermissions as $permissionName) {
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
                $gymPermissions[] = $permission;
            }

            $role->syncPermissions($gymPermissions);
            $this->updateReceptionistUsers($role, $currentTenant);
            $this->loadReceptionistPermissions();

            DB::commit();

            Notification::make()
                ->title('Permissions Reset')
                ->body('Receptionist permissions have been reset to default settings.')
                ->success()
                ->send();
        } catch (Exception $e) {
            DB::rollBack();

            Log::error($e);

            NotificationHelper::unexpectedError('resetting permissions', $e)->send();
        }
    }

    private function quickSetupPermissions(): void
    {
        try {
            // Define quick setup permissions for common receptionist tasks
            $quickSetupPermissions = [
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
                'view_class',
                'view_any_class',
                'book_class',
                'manage_class_bookings',
                'view_member_stats_widget',
                'view_recent_members_widget',
                'view_upcoming_classes_widget',
                'view_overdue_payments_widget',
                'access_notifications',
                'manage_member_notes',
                'send_notifications',
            ];

            $currentTenant = Filament::getTenant();

            DB::beginTransaction();

            $role = Role::firstOrCreate([
                'name' => 'receptionist',
                'guard_name' => 'web',
                'gym_id' => $currentTenant->id,
            ]);

            $gymPermissions = [];
            foreach ($quickSetupPermissions as $permissionName) {
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
                $gymPermissions[] = $permission;
            }

            $role->syncPermissions($gymPermissions);
            $this->updateReceptionistUsers($role, $currentTenant);
            $this->loadReceptionistPermissions();

            DB::commit();

            Notification::make()
                ->title('Quick Setup Complete')
                ->body('Common receptionist permissions have been applied.')
                ->success()
                ->send();
        } catch (Exception $e) {
            DB::rollBack();

            Log::error($e);

            NotificationHelper::unexpectedError('quick setup permissions', $e)->send();
        }
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

    private function getPermissionsByCategory(): array
    {
        // Get all permissions from database grouped by category
        $permissions = Permission::select('name', 'display_name', 'description', 'category')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        return $permissions->toArray();
    }

    // Build category-based sections dynamically
    private function buildCategoryTabs(array $permissionsByCategory, ?array $includeCategory = []): array
    {
        $filteredCategory = Arr::only($permissionsByCategory, $includeCategory);
        // dd($permissionsByCategory, $filteredCategory);
        $sections = [];

        foreach ($filteredCategory as $category => $permissions) {
            if (empty($category)) {
                continue; // Skip permissions without category
            }

            try {
                $permissionCategory = PermissionCategory::from($category);
                $categoryName = $permissionCategory->getLabel();
                $categoryIcon = $permissionCategory->getIcon();
            } catch (ValueError) {
                $categoryName = ucfirst(str_replace('_', ' ', $category));
                $categoryIcon = 'heroicon-o-folder';
            }

            $fieldName = $category . '_permissions';

            $options = [];
            $descriptions = [];

            foreach ($permissions as $permission) {
                $options[$permission['name']] = $permission['display_name'] ?? ucfirst(str_replace('_', ' ', $permission['name']));
                $descriptions[$permission['name']] = $permission['description'] ?? '';
            }

            $sections[] = Section::make($categoryName . ' Resource')
                ->description('Manage ' . strtolower($categoryName) . '-related permissions for receptionists')
                ->icon($categoryIcon)
                ->schema([
                    CheckboxList::make($fieldName)
                        ->label($categoryName . ' Permissions')
                        ->options($options)
                        ->columns(2)
                        ->descriptions($descriptions)
                        ->gridDirection('row')
                        ->bulkToggleable(),
                ])
                ->collapsible();
        }

        return $sections;
    }

    // Get appropriate icon for each category
    private function getCategoryIcon(string $category): string
    {
        try {
            $permissionCategory = PermissionCategory::from($category);
            return $permissionCategory->getIcon() ?? 'heroicon-o-folder';
        } catch (ValueError) {
            // If category doesn't exist in enum, return default icon
            return 'heroicon-o-folder';
        }
    }
}
