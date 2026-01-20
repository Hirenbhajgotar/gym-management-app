<?php

namespace Database\Seeders;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\Permission as ModelsPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[
            \Spatie\Permission\PermissionRegistrar::class
        ]->forgetCachedPermissions();

        // Get all teams/tenants
        $gyms = Gym::all();

        if ($gyms->isEmpty()) {
            if (isset($this->command)) {
                $this->command->warn('⚠️ No gyms found. Creating default gym...');
            }
            $gym = Gym::create([
                'name' => 'Default gym',
                'slug' => 'default-gym',
            ]);
            $gyms = collect([$gym]);
        }

        // Define your models/resources - these should match your Filament Resources
        $resources = [
            'user',
            'city',
            'state',
            'country',
            'plan',
            'gym_member',
            'gym',
            'membership_plan',
            'trainer',
            'receptionist',
            'manager',
            'attendance',
            'payment',
            'permission',
            'lead',
            // 'equipment',
            // 'workout',
            // 'schedule',
            // 'subscription',
            // 'invoice',
            // 'report',
            // Add more resources as needed
        ];

        // Define Filament v3 standard permissions
        $resourceActions = [
            'view_any', // List/Index page
            'view', // View single record
            'create', // Create new record
            'update', // Edit existing record
            'delete', // Delete record
            'delete_any',
            'force_delete', // Permanently delete
            'force_delete_any', // Force delete multiple records
            'restore', // Restore soft deleted
            'replicate', // Replicate record
            'reorder', // Reorder records
            'export',
            'import',
        ];

        // Create permissions for each resource
        $allPermissions = [];
        $gymOwnerPermissions = [];
        $gymManagerPermissions = [];

        foreach ($resources as $resource) {
            foreach ($resourceActions as $action) {
                $permissionName = $action . '_' . $resource;
                $permission = ModelsPermission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
                $allPermissions[] = $permission;

                // Add gym owner permissions
                if (
                    in_array($resource, [
                        'user',
                        'trainer',
                        'receptionist',
                        'manager',
                        'gym_member',
                        'gym',
                        'membership_plan',
                        'attendance',
                        'payment',
                        'permission',
                    ])
                ) {
                    $gymOwnerPermissions[] = $permission;
                }

                // Add gym manager permissions
                if (
                    in_array($resource, [
                        'user',
                        'trainer',
                        'receptionist',
                        'gym_member',
                        'gym',
                        'membership_plan',
                        'attendance',
                        'payment',
                    ])
                ) {
                    $gymManagerPermissions[] = $permission;
                }
            }
        }

        // Add Filament v3 specific permissions
        $filamentPermissions = [
            // Admin Panel Access
            'access_admin_panel',

            // Dashboard & Widgets
            'view_dashboard',
            'widget_gym_revenue_chart',
            'widget_gym_stats_overview',
            'widget_membership_expiry_alerts_chart',
            'widget_recent_activity',
            'widget_todays_birthdays',
            'widget_trainer_performance',

            // Pages
            'page_dashboard',
            'page_edit_gym_profile',
            'page_register_gym',
            'page_settings',
            'page_reports',
            'page_analytics',
            'page_backup',
            'page_system_health',

            // System Operations
            'manage_settings',
            'view_analytics',
            'export_data',
            'import_data',
            'manage_backups',
            'view_system_logs',
            'clear_cache',
            'run_commands',

            // Notifications & Communication
            'send_notifications',
            'send_bulk_emails',
            'manage_announcements',

            // Advanced Features
            'bulk_actions',
            'advanced_search',
            'manage_integrations',
            'api_access',

            // Reports & Analytics
            'view_financial_reports',
            'view_member_reports',
            'view_trainer_reports',
            'view_equipment_reports',
            'export_reports',
        ];

        $gymOwnerExceptPermissions = [
            'access_admin_panel',
            'clear_cache',
            'run_commands',
            'manage_integrations',
            'page_system_health',
        ];
        $filamentManagerPermissions = [
            'access_admin_panel',
            'clear_cache',
            'run_commands',
            'manage_integrations',
            'page_system_health',
            'page_register_gym',
            'page_edit_gym_profile',
        ];

        foreach ($filamentPermissions as $permissionName) {
            $permission = ModelsPermission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
            $allPermissions[] = $permission;

            // Add gym owner permissions
            if (! in_array($resource, $gymOwnerExceptPermissions)) {
                $gymOwnerPermissions[] = $permission;
            }

            if (! in_array($resource, $filamentManagerPermissions)) {
                $gymManagerPermissions[] = $permission;
            }
        }

        // Create Super Admin role with all permissions
        $superAdminRole = Role::firstOrCreate([
            'name' => DefaultRole::SUPER_ADMIN->value,
            'guard_name' => 'web',
        ]);
        $superAdminRole->syncPermissions($allPermissions);

        // Process each gym
        foreach ($gyms as $gym) {
            $this->createGymRoles(
                $gym,
                $gymOwnerPermissions,
                $gymManagerPermissions,
            );
        }
        if (isset($this->command)) {
            $this->command->info(
                '✅ Tenant-specific roles and permissions have been seeded successfully!',
            );
        }

        // Display summary
        $this->displaySummary($gyms);
    }

    /**
     * Create roles for a specific gym
     */
    private function createGymRoles(
        Gym $gym,
        array $gymOwnerPermissions,
        array $gymManagerPermissions,
    ) {
        // Create Gym Owner role with all permissions (same as super admin)
        $gymOwnerRole = Role::firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);
        $gymOwnerRole->syncPermissions($gymOwnerPermissions);

        $managerRole = Role::firstOrCreate([
            'name' => DefaultRole::MANAGER->value,
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);
        $managerRole->syncPermissions($gymManagerPermissions);

        // Create Trainer role with limited permissions
        $trainerRole = Role::firstOrCreate([
            'name' => 'trainer',
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);

        $trainerPermissions = [
            // Basic access
            'access_admin_panel',
            'view_dashboard',
            'page_dashboard',

            // Members (limited to assigned members)
            'view_any_member',
            'view_member',
            'update_member',

            // Workouts
            'view_any_workout',
            'view_workout',
            'create_workout',
            'update_workout',
            'delete_workout',

            // Schedules
            'view_any_schedule',
            'view_schedule',
            'create_schedule',
            'update_schedule',
            'delete_schedule',

            // Attendance
            'view_any_attendance',
            'view_attendance',
            'create_attendance',
            'update_attendance',

            // Own profile
            'view_trainer',
            'update_trainer',

            // Equipment (view only)
            'view_any_equipment',
            'view_equipment',

            // Widgets
            'widget_stats_overview',
            'widget_recent_members',
        ];

        $trainerRole->syncPermissions(
            Permission::whereIn('name', $trainerPermissions)->get(),
        );

        // Create Receptionist role with customer service permissions
        $receptionistRole = Role::firstOrCreate([
            'name' => 'receptionist',
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);

        $receptionistPermissions = [
            // Basic access
            'access_admin_panel',
            'view_dashboard',
            'page_dashboard',

            // Members (full CRUD)
            'view_any_member',
            'view_member',
            'create_member',
            'update_member',
            'delete_member',

            // Memberships
            'view_any_membership',
            'view_membership',
            'create_membership',
            'update_membership',

            // Membership Plans (view only)
            'view_any_membership_plan',
            'view_membership_plan',

            // Payments
            'view_any_payment',
            'view_payment',
            'create_payment',
            'update_payment',

            // Attendance
            'view_any_attendance',
            'view_attendance',
            'create_attendance',

            // Trainers (view only)
            'view_any_trainer',
            'view_trainer',

            // Equipment (view only)
            'view_any_equipment',
            'view_equipment',

            // Own profile
            'view_receptionist',
            'update_receptionist',

            // Widgets
            'widget_stats_overview',
            'widget_recent_members',

            // Communication
            'send_notifications',
        ];

        $receptionistRole->syncPermissions(
            Permission::whereIn('name', $receptionistPermissions)->get(),
        );

        // Create Member role (if you want members to have limited access)
        $memberRole = Role::firstOrCreate([
            'name' => 'member',
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);

        $memberPermissions = [
            // Very limited access - only their own data
            'access_admin_panel',
            'view_dashboard',

            // Own profile only
            'view_member',
            'update_member',

            // Own workouts and schedules
            'view_workout',
            'view_schedule',
            'view_attendance',

            // View trainers (to see who they're assigned to)
            'view_trainer',
        ];

        $memberRole->syncPermissions(
            Permission::whereIn('name', $memberPermissions)->get(),
        );
    }

    /**
     * Display summary of created roles and permissions
     */
    private function displaySummary($gyms)
    {
        if (! isset($this->command)) {
            return;
        }

        $this->command->info('📊 Summary:');

        foreach ($gyms as $gym) {
            $this->command->info("   🏢 Gym: {$gym->name}");

            $gymRoles = Role::where('gym_id', $gym->id)->get();
            foreach ($gymRoles as $role) {
                $permissionCount = $role->permissions()->count();
                $this->command->info(
                    "      🔹 {$role->name} ({$permissionCount} permissions)",
                );
            }
        }

        $totalPermissions = Permission::count();
        $totalRoles = Role::count();
        $this->command->info(
            "📈 Total permissions created: {$totalPermissions}",
        );
        $this->command->info("👥 Total roles created: {$totalRoles}");
    }
}
