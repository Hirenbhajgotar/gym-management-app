<?php

namespace App\Filament\Admin\Clusters\Permissions\Pages;

use App\Filament\Admin\Clusters\Permissions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
                Section::make('Members Resource')
                    ->description('Manage member-related permissions for receptionists')
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
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Memberships Resource')
                    ->description('Manage membership-related permissions for receptionists')
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
                                // 'manage_membership_plans' => 'Manage Membership Plans',
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
                                // 'manage_membership_plans' => 'Can manage membership plans',
                            ]),
                    ])
                    ->collapsible(),
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

        $this->form->fill([
            'gym_member_permissions' => $memberPermissions,
            'membership_plan_permissions' => $membershipPermissions,
            // 'payment_permissions' => $paymentPermissions,
            // 'class_permissions' => $classPermissions,
            'report_permissions' => $reportPermissions,
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $currentTenant = Filament::getTenant();

        // Get or create the receptionist role for the current gym
        $role = Role::firstOrCreate([
            'name' => 'receptionist',
            'guard_name' => 'web',
            'gym_id' => $currentTenant->id,
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
                'gym_id' => $currentTenant->id,
            ]);
            $gymPermissions[] = $permission;
        }

        // Sync permissions only for this gym's role
        $role->syncPermissions($gymPermissions);

        // Update all receptionist users in this gym
        $this->updateReceptionistUsers($role, $currentTenant);

        Notification::make()
            ->title('Receptionist Permissions Updated')
            ->body('Permissions have been updated for the current gym.')
            ->success()
            ->send();
    }

    private function updateReceptionistUsers(Role $role, $tenant): void
    {
        // Get all users with receptionist role in current gym
        $receptionistUsers = User::whereHas('roles', function ($query) use ($tenant) {
            $query->where('name', 'receptionist')
                ->where('gym_id', $tenant->id);
        })
            ->get();

        foreach ($receptionistUsers as $user) {
            // Sync permissions for this user in this gym context
            $user->syncPermissions($role->permissions, $tenant);
        }
    }

    public static function canAccess(): bool
    {
        $currentTenant = Filament::getTenant();

        // if (!$currentTenant) {
        //     return false;
        // }

        // Check if user has gym_owner role for this specific gym
        // return auth()->user()->hasRole('gym_owner', $currentTenant);
        return true; // For now, allow access to all authenticated users
    }

    protected function getActions(): array
    {
        return [
            Action::make('reset_permissions')
                ->label('Reset to Default')
                ->color('gray')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    $this->resetToDefaultPermissions();
                }),
        ];
    }

    private function resetToDefaultPermissions(): void
    {
        $currentTenant = Filament::getTenant();

        // Define default permissions for receptionist
        $defaultPermissions = [
            'view_gym_member',
            'create_gym_member',
            'edit_gym_member',
            'view_membership_plan',
            'create_membership_plan',
            'view_payments',
            'create_payments',
            'view_classes',
            'book_classes',
        ];

        $role = Role::firstOrCreate([
            'name' => 'receptionist',
            'guard_name' => 'web',
            'gym_id' => $currentTenant->id,
        ]);

        // Create default permissions for this team
        $gymPermissions = [];
        foreach ($defaultPermissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
                'gym_id' => $currentTenant->id,
            ]);
            $gymPermissions[] = $permission;
        }

        $role->syncPermissions($gymPermissions);
        $this->updateReceptionistUsers($role, $currentTenant);
        $this->loadReceptionistPermissions();

        Notification::make()
            ->title('Permissions Reset')
            ->body('Receptionist permissions have been reset to default.')
            ->success()
            ->send();
    }
}
