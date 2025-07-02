<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define your Filament Resources - these should match your actual Resource class names
        $resources = [
            'User',
            'City',
            'State',
            'Country',
            'Plan',
            'GymMember',
            'Gym',
            'MembershipPlan',
            'Trainer',
            'Receptionist',
            'Payment',
            'Equipment',
            'Workout',
            'Schedule',
            'Attendance',
            'Subscription',
            'Invoice',
            'Report',
            'Class',
            'Booking',
            'Notification',
            'Setting',
        ];

        // Shield standard resource permissions (following Shield plugin naming)
        $resourceActions = [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'delete_any',
            'force_delete',
            'force_delete_any',
            'restore',
            'restore_any',
            'replicate',
            'reorder',
            'export',
            'import',
        ];

        // Create resource permissions
        $allPermissions = [];

        foreach ($resources as $resource) {
            foreach ($resourceActions as $action) {
                // Shield naming convention: action::Model
                $permissionName = $action . '::' . $resource;
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web'
                ]);
                $allPermissions[] = $permission;
            }
        }

        // Shield Page permissions
        $pages = [
            'Dashboard',
            'Settings',
            'Reports',
            'Analytics',
            'GymProfile',
            'RegisterGym',
            'Backup',
            'SystemHealth',
            'UserProfile',
            'ImportExport',
        ];

        foreach ($pages as $page) {
            $permissionName = 'page_' . $page;
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ]);
            $allPermissions[] = $permission;
        }

        // Shield Widget permissions
        $widgets = [
            'GymStatsOverview',
            'GymRevenueChart',
            'MembershipExpiryAlertsChart',
            'RecentActivity',
            'TodaysBirthdays',
            'TrainerPerformance',
            'MemberGrowthChart',
            'PaymentSummary',
            'EquipmentStatus',
            'ClassBookings',
            'AttendanceChart',
            'MonthlyRevenue',
        ];

        foreach ($widgets as $widget) {
            $permissionName = 'widget_' . $widget;
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ]);
            $allPermissions[] = $permission;
        }

        // Shield custom permissions (business logic specific)
        $customPermissions = [
            // Panel access
            'access_admin_panel',
            'access_member_panel',

            // Gym specific
            'manage_gym_settings',
            'switch_gym_context',
            'view_gym_analytics',
            'manage_gym_staff',

            // Member management
            'bulk_member_actions',
            'member_check_in',
            'member_check_out',
            'freeze_membership',
            'unfreeze_membership',
            'transfer_membership',

            // Payment operations
            'process_payments',
            'refund_payments',
            'view_payment_reports',
            'manage_payment_methods',

            // Communication
            'send_notifications',
            'send_bulk_emails',
            'send_sms',
            'manage_announcements',

            // Reports and analytics
            'export_member_data',
            'view_financial_reports',
            'view_attendance_reports',
            'view_trainer_reports',
            'view_equipment_reports',
            'generate_custom_reports',

            // System operations
            'manage_system_settings',
            'view_system_logs',
            'clear_cache',
            'run_maintenance',
            'manage_backups',
            'manage_integrations',

            // Advanced features
            'api_access',
            'webhook_management',
            'custom_fields_management',
            'role_management',
            'permission_management',

            // Equipment management
            'schedule_maintenance',
            'track_equipment_usage',
            'manage_equipment_warranty',

            // Class and booking management
            'manage_class_schedules',
            'book_classes_for_members',
            'cancel_bookings',
            'manage_waiting_lists',

            // Trainer specific
            'assign_members_to_trainer',
            'view_trainer_schedule',
            'manage_trainer_availability',
            'track_trainer_performance',
        ];

        foreach ($customPermissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ]);
            $allPermissions[] = $permission;
        }

        // Create roles following Shield conventions

        // 1. Super Admin - Has all permissions
        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web'
        ]);
        $superAdminRole->syncPermissions($allPermissions);

        // 2. Gym Owner - Has almost all permissions except system management
        $gymOwnerRole = Role::firstOrCreate([
            'name' => 'gym_owner',
            'guard_name' => 'web'
        ]);

        $gymOwnerPermissions = collect($allPermissions)->filter(function ($permission) {
            $excludedPermissions = [
                'manage_system_settings',
                'view_system_logs',
                'clear_cache',
                'run_maintenance',
                'role_management',
                'permission_management',
                'manage_integrations',
            ];

            return !in_array($permission->name, $excludedPermissions);
        });

        $gymOwnerRole->syncPermissions($gymOwnerPermissions);

        // 3. Trainer - Limited to training related functions
        $trainerRole = Role::firstOrCreate([
            'name' => 'trainer',
            'guard_name' => 'web'
        ]);

        $trainerPermissions = [
            'access_admin_panel',
            'page_Dashboard',

            // Members (view and limited update)
            'view_any::GymMember',
            'view::GymMember',
            'update::GymMember',
            'member_check_in',
            'member_check_out',

            // Workouts
            'view_any::Workout',
            'view::Workout',
            'create::Workout',
            'update::Workout',
            'delete::Workout',

            // Schedules
            'view_any::Schedule',
            'view::Schedule',
            'create::Schedule',
            'update::Schedule',
            'delete::Schedule',

            // Classes
            'view_any::Class',
            'view::Class',
            'create::Class',
            'update::Class',
            'manage_class_schedules',

            // Attendance
            'view_any::Attendance',
            'view::Attendance',
            'create::Attendance',
            'update::Attendance',

            // Equipment (view only)
            'view_any::Equipment',
            'view::Equipment',

            // Own profile
            'view::Trainer',
            'update::Trainer',
            'view_trainer_schedule',
            'manage_trainer_availability',

            // Widgets
            'widget_TrainerPerformance',
            'widget_GymStatsOverview',
            'widget_AttendanceChart',
            'widget_ClassBookings',

            // Communication
            'send_notifications',
        ];

        $trainerRole->syncPermissions(
            Permission::whereIn('name', $trainerPermissions)->get()
        );

        // 4. Receptionist - Customer service focused
        $receptionistRole = Role::firstOrCreate([
            'name' => 'receptionist',
            'guard_name' => 'web'
        ]);

        $receptionistPermissions = [
            'access_admin_panel',
            'page_Dashboard',

            // Members (full management)
            'view_any::GymMember',
            'view::GymMember',
            'create::GymMember',
            'update::GymMember',
            'delete::GymMember',
            'member_check_in',
            'member_check_out',
            'freeze_membership',
            'unfreeze_membership',
            'bulk_member_actions',

            // Memberships
            'view_any::Subscription',
            'view::Subscription',
            'create::Subscription',
            'update::Subscription',
            'transfer_membership',

            // Membership Plans
            'view_any::MembershipPlan',
            'view::MembershipPlan',

            // Payments
            'view_any::Payment',
            'view::Payment',
            'create::Payment',
            'update::Payment',
            'process_payments',
            'refund_payments',

            // Classes and Bookings
            'view_any::Class',
            'view::Class',
            'view_any::Booking',
            'view::Booking',
            'create::Booking',
            'update::Booking',
            'delete::Booking',
            'book_classes_for_members',
            'cancel_bookings',
            'manage_waiting_lists',

            // Attendance
            'view_any::Attendance',
            'view::Attendance',
            'create::Attendance',

            // Staff (view only)
            'view_any::Trainer',
            'view::Trainer',

            // Equipment (view only)
            'view_any::Equipment',
            'view::Equipment',

            // Own profile
            'view::Receptionist',
            'update::Receptionist',

            // Widgets
            'widget_GymStatsOverview',
            'widget_RecentActivity',
            'widget_TodaysBirthdays',
            'widget_ClassBookings',
            'widget_PaymentSummary',

            // Communication
            'send_notifications',
            'send_bulk_emails',
            'send_sms',

            // Reports (limited)
            'view_attendance_reports',
            'export_member_data',
        ];

        $receptionistRole->syncPermissions(
            Permission::whereIn('name', $receptionistPermissions)->get()
        );

        // 5. Member - Very limited access (if you have member portal)
        $memberRole = Role::firstOrCreate([
            'name' => 'member',
            'guard_name' => 'web'
        ]);

        $memberPermissions = [
            'access_member_panel',
            'page_Dashboard',

            // Own profile only
            'view::GymMember',
            'update::GymMember',

            // Own data
            'view::Workout',
            'view::Schedule',
            'view::Attendance',
            'view::Booking',
            'create::Booking',
            'delete::Booking',

            // View trainers and classes
            'view::Trainer',
            'view_any::Class',
            'view::Class',

            // Limited widgets
            'widget_GymStatsOverview',
        ];

        $memberRole->syncPermissions(
            Permission::whereIn('name', $memberPermissions)->get()
        );

        // 6. Manager - Between gym owner and receptionist
        $managerRole = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web'
        ]);

        $managerPermissions = collect($receptionistPermissions)->merge([
            // Additional manager permissions
            'view_any::User',
            'view::User',
            'create::User',
            'update::User',
            'manage_gym_staff',

            // Equipment management
            'create::Equipment',
            'update::Equipment',
            'delete::Equipment',
            'schedule_maintenance',
            'track_equipment_usage',

            // Advanced reports
            'view_financial_reports',
            'view_trainer_reports',
            'view_equipment_reports',
            'generate_custom_reports',

            // Settings
            'manage_gym_settings',

            // All widgets
            'widget_RevenueChart',
            'widget_MembershipExpiryAlerts',
            'widget_TrainerPerformance',
            'widget_MemberGrowthChart',
            'widget_EquipmentStatus',
            'widget_MonthlyRevenue',
        ])->unique()->toArray();

        $managerRole->syncPermissions(
            Permission::whereIn('name', $managerPermissions)->get()
        );

        $this->command->info('🛡️  Filament Shield style roles and permissions have been seeded successfully!');
        $this->command->info('');
        $this->command->info('📊 Created roles with permissions:');
        $this->command->info("   🔴 super_admin ({$superAdminRole->permissions->count()} permissions) - Full system access");
        $this->command->info("   🟠 gym_owner ({$gymOwnerRole->permissions->count()} permissions) - Gym management");
        $this->command->info("   🟡 manager ({$managerRole->permissions->count()} permissions) - Operational management");
        $this->command->info("   🟢 trainer ({$trainerRole->permissions->count()} permissions) - Training focused");
        $this->command->info("   🔵 receptionist ({$receptionistRole->permissions->count()} permissions) - Customer service");
        $this->command->info("   🟣 member ({$memberRole->permissions->count()} permissions) - Self-service portal");
        $this->command->info('');
        $this->command->info("📈 Total permissions created: " . Permission::count());
        $this->command->info('');
        $this->command->info('🎯 Permission Structure:');
        $this->command->info('   📋 Resource permissions: action::ModelName');
        $this->command->info('   📄 Page permissions: page_PageName');
        $this->command->info('   📊 Widget permissions: widget_WidgetName');
        $this->command->info('   ⚙️  Custom permissions: snake_case_naming');
    }
}
