<?php

namespace App\Filament\Admin\Clusters\Permissions\Pages;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        return $form
            ->schema([
                Tabs::make('Permissions')
                    ->tabs([
                        Tabs\Tab::make('Resources')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Section::make('Members Resource')
                                    ->description('Manage member-related permissions for receptionists')
                                    ->icon('heroicon-o-users')
                                    ->schema([
                                        CheckboxList::make('gym_member_permissions')
                                            ->label('Member Permissions')
                                            ->options([
                                                'view_gym_member' => 'View Member',
                                                'view_any_gym_member' => 'View Any Member',
                                                'create_gym_member' => 'Create Member',
                                                'edit_gym_member' => 'Edit Member',
                                                'delete_gym_member' => 'Delete Member',
                                                'restore_gym_member' => 'Restore Member',
                                                'force_delete_gym_member' => 'Force Delete Member',
                                                'replicate_gym_member' => 'Replicate Member',
                                                'reorder_gym_member' => 'Reorder Member',
                                                'export_gym_member' => 'Export Member',
                                            ])
                                            ->columns(2)
                                            ->descriptions([
                                                'view_gym_member' => 'Can view member profiles and information',
                                                'view_any_gym_member' => 'Can view all members in the system',
                                                'create_gym_member' => 'Can register new members',
                                                'edit_gym_member' => 'Can update member information',
                                                'delete_gym_member' => 'Can delete member records',
                                                'restore_gym_member' => 'Can restore deleted member records',
                                                'force_delete_gym_member' => 'Can permanently delete member records',
                                                'replicate_gym_member' => 'Can replicate member records',
                                                'reorder_gym_member' => 'Can reorder member records',
                                                'export_gym_member' => 'Can export member data',
                                            ])
                                            ->gridDirection('row'),
                                    ])
                                    ->collapsible(),

                                Section::make('Memberships Resource')
                                    ->description('Manage membership-related permissions for receptionists')
                                    ->icon('heroicon-o-credit-card')
                                    ->schema([
                                        CheckboxList::make('membership_plan_permissions')
                                            ->label('Membership Permissions')
                                            ->options([
                                                'view_membership_plan' => 'View Memberships',
                                                'view_any_membership_plan' => 'View Any Memberships',
                                                'create_membership_plan' => 'Create Memberships',
                                                'edit_membership_plan' => 'Edit Memberships',
                                                'delete_membership_plan' => 'Delete Memberships',
                                                'restore_membership_plan' => 'Restore Memberships',
                                                'force_delete_membership_plan' => 'Force Delete Memberships',
                                                'replicate_membership_plan' => 'Replicate Memberships',
                                                'reorder_membership_plan' => 'Reorder Memberships',
                                            ])
                                            ->columns(2)
                                            ->descriptions([
                                                'view_membership_plan' => 'Can view membership records',
                                                'view_any_membership_plan' => 'Can view all membership records',
                                                'create_membership_plan' => 'Can create new memberships',
                                                'edit_membership_plan' => 'Can modify membership details',
                                                'delete_membership_plan' => 'Can delete memberships',
                                                'restore_membership_plan' => 'Can restore deleted memberships',
                                                'force_delete_membership_plan' => 'Can permanently delete memberships',
                                                'replicate_membership_plan' => 'Can replicate membership records',
                                                'reorder_membership_plan' => 'Can reorder membership records',
                                            ])
                                            ->gridDirection('row'),
                                    ])
                                    ->collapsible(),

                                // Section::make('Payments Resource')
                                //     ->description('Manage payment-related permissions for receptionists')
                                //     ->icon('heroicon-o-banknotes')
                                //     ->schema([
                                //         CheckboxList::make('payment_permissions')
                                //             ->label('Payment Permissions')
                                //             ->options([
                                //                 'view_payment' => 'View Payments',
                                //                 'view_any_payment' => 'View Any Payments',
                                //                 'create_payment' => 'Create Payments',
                                //                 'edit_payment' => 'Edit Payments',
                                //                 'delete_payment' => 'Delete Payments',
                                //                 'restore_payment' => 'Restore Payments',
                                //                 'force_delete_payment' => 'Force Delete Payments',
                                //                 'export_payment' => 'Export Payments',
                                //             ])
                                //             ->columns(2)
                                //             ->descriptions([
                                //                 'view_payment' => 'Can view payment records',
                                //                 'view_any_payment' => 'Can view all payment records',
                                //                 'create_payment' => 'Can process new payments',
                                //                 'edit_payment' => 'Can modify payment details',
                                //                 'delete_payment' => 'Can delete payment records',
                                //                 'restore_payment' => 'Can restore deleted payments',
                                //                 'force_delete_payment' => 'Can permanently delete payments',
                                //                 'export_payment' => 'Can export payment data',
                                //             ])
                                //             ->gridDirection('row'),
                                //     ])
                                //     ->collapsible(),

                                // Section::make('Classes Resource')
                                //     ->description('Manage class-related permissions for receptionists')
                                //     ->icon('heroicon-o-academic-cap')
                                //     ->schema([
                                //         CheckboxList::make('class_permissions')
                                //             ->label('Class Permissions')
                                //             ->options([
                                //                 'view_class' => 'View Classes',
                                //                 'view_any_class' => 'View Any Classes',
                                //                 'create_class' => 'Create Classes',
                                //                 'edit_class' => 'Edit Classes',
                                //                 'delete_class' => 'Delete Classes',
                                //                 'restore_class' => 'Restore Classes',
                                //                 'book_class' => 'Book Classes',
                                //                 'manage_class_bookings' => 'Manage Class Bookings',
                                //             ])
                                //             ->columns(2)
                                //             ->descriptions([
                                //                 'view_class' => 'Can view class schedules',
                                //                 'view_any_class' => 'Can view all classes',
                                //                 'create_class' => 'Can schedule new classes',
                                //                 'edit_class' => 'Can modify class details',
                                //                 'delete_class' => 'Can cancel classes',
                                //                 'restore_class' => 'Can restore cancelled classes',
                                //                 'book_class' => 'Can book classes for members',
                                //                 'manage_class_bookings' => 'Can manage member class bookings',
                                //             ])
                                //             ->gridDirection('row'),
                                //     ])
                                //     ->collapsible(),
                            ]),

                        Tabs\Tab::make('Widgets')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Section::make('Dashboard Widgets')
                                    ->description('Control access to dashboard widgets and analytics')
                                    ->icon('heroicon-o-presentation-chart-bar')
                                    ->schema([
                                        CheckboxList::make('widget_permissions')
                                            ->label('Widget Access')
                                            ->options([
                                                'widget_stats_overview' => 'Member Statistics Widget',
                                                'widget_gym_revenue_chart' => 'Revenue Widget',
                                                'widget_recent_members' => 'Recent Members Widget',
                                                'widget_membership_expiry_alerts_chart' => 'Overdue Payments Widget',
                                                'widget_todays_birthdays' => 'Membership Birthday Widget',
                                                'widget_trainer_performance' => 'Trainer Performance Widget',
                                            ])
                                            ->columns(2)
                                            ->descriptions([
                                                'widget_stats_overview' => 'Can view member statistics on dashboard',
                                                'widget_gym_revenue_chart' => 'Can view revenue information on dashboard',
                                                'widget_recent_members' => 'Can view recent members on dashboard',
                                                'widget_membership_expiry_alerts_chart' => 'Can view Overdue Payments on dashboard',
                                                'widget_todays_birthdays' => 'Can view Members birthday on dashboard',
                                                'widget_trainer_performance' => 'Can view trainers performance on dashboard',
                                            ])
                                            ->gridDirection('row'),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tabs\Tab::make('Reports')
                            ->icon('heroicon-o-document-chart-bar')
                            ->schema([
                                Section::make('Report Access')
                                    ->description('Configure report generation and viewing permissions')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->schema([
                                        CheckboxList::make('report_permissions')
                                            ->label('Report Permissions')
                                            ->options([
                                                'page_reports' => 'Access Reports Page',
                                                'view_financial_reports' => 'View Financial Reports',
                                                'view_member_reports' => 'View Member Reports',
                                                'view_trainer_reports' => 'View Trainer Reports',
                                                'view_equipment_reports' => 'View Equipment Reports',
                                                'export_reports' => 'Export Reports',
                                            ])
                                            ->columns(2)
                                            ->descriptions([
                                                'page_reports' => 'Can access the reports section',
                                                'view_financial_reports' => 'Can view financial and revenue reports',
                                                'view_member_reports' => 'Can view member-related reports',
                                                'view_trainer_reports' => 'Can view trainer performance reports',
                                                'view_equipment_reports' => 'Can view equipment usage reports',
                                                'export_reports' => 'Can export reports to various formats',
                                            ])
                                            ->gridDirection('row'),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tabs\Tab::make('Other')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Section::make('System Features')
                                    ->description('Additional system features and administrative permissions')
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->schema([
                                        CheckboxList::make('system_permissions')
                                            ->label('System Permissions')
                                            ->options([
                                                'manage_settings' => 'Manage Gym Settings',
                                                'view_analytics' => 'View Analytics',
                                                'export_data' => 'Export Data',
                                                'import_data' => 'Import Data',
                                                'manage_backups' => 'Backup Data',
                                            ])
                                            ->columns(2)
                                            ->descriptions([
                                                'manage_settings' => 'Can modify configuration settings',
                                                'view_analytics' => 'Can view analytics',
                                                'export_data' => 'Can export data',
                                                'import_data' => 'Can import data',
                                                'manage_backups' => 'Can create data backups',
                                            ])
                                            ->gridDirection('row'),
                                    ])
                                    ->collapsible(),

                                Section::make('Communication')
                                    ->description('Communication and messaging permissions')
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->schema([
                                        CheckboxList::make('communication_permissions')
                                            ->label('Communication Permissions')
                                            ->options([
                                                'send_notifications' => 'Send Notifications',
                                                'send_bulk_emails' => 'Send Bulk Emails',
                                                'manage_announcements' => 'Manage Announcements',
                                            ])
                                            ->columns(2)
                                            ->descriptions([
                                                'send_notifications' => 'Can send individual notifications to members',
                                                'send_bulk_emails' => 'Can send bulk emails to multiple members',
                                                'manage_announcements' => 'Can create and manage gym announcements',
                                            ])
                                            ->gridDirection('row'),
                                    ])
                                    ->collapsible(),
                            ]),
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
}
