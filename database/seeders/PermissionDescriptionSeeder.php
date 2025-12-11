<?php

namespace Database\Seeders;

use App\Enums\PermissionCategory;
use App\Models\Permission;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class PermissionDescriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            $resources = [
                'user' => 'User',
                'city' => 'City',
                'state' => 'State',
                'country' => 'Country',
                'plan' => 'Plan',
                'gym_member' => 'Member',
                'gym' => 'Gym',
                'membership_plan' => 'Memberships',
                'trainer' => 'Trainer',
                'receptionist' => 'Receptionist',
                'payment' => 'Payment',
                'permission' => 'Permission',
                'lead' => 'Lead',
                // 'equipment',
                // 'workout',
                // 'schedule',
                'attendance' => 'Attendance',
                'manager' => 'Manager',
                // 'subscription',
                // 'invoice',
                // 'report',
            ];

            // Define Filament v3 standard permissions
            $resourceActions = [
                'view_any',    // List/Index page
                'view',        // View single record
                'create',      // Create new record
                'update',      // Edit existing record
                'delete',      // Delete record
                'delete_any',
                'force_delete', // Permanently delete
                'force_delete_any', // Force delete multiple records
                'restore',     // Restore soft deleted
                'replicate',   // Replicate record
                'reorder',     // Reorder records
                'export',
                'import',
            ];

            foreach ($resources as $resourceKey => $resource) {
                foreach ($resourceActions as $action) {

                    $permissionName = $action . '_' . $resourceKey;
                    $displayName = ucwords(str_replace(['_', '-'], ' ', $action . '_' . $resource));
                    $description = ucwords(str_replace(['_', '-'], ' ', 'can_' . $displayName));
                    $category = PermissionCategory::tryFrom($resourceKey)->value ?? null;

                    // dd($permissionName, $displayName, $description, $category);

                    $permission = Permission::updateOrCreate([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ], [
                        'display_name' => $displayName,
                        'description' => $description,
                        'category' => $category,
                    ]);
                    // $allPermissions[] = $permission;
                }
            }

            $filamentPermissions = [
                // Admin Panel Access
                'access_admin_panel' => 'general',

                // Dashboard & Widgets
                'view_dashboard' => 'general',
                'widget_gym_revenue_chart' => 'widgets',
                'widget_gym_stats_overview' => 'widgets',
                'widget_membership_expiry_alerts_chart' => 'widgets',
                'widget_recent_activity' => 'widgets',
                'widget_todays_birthdays' => 'widgets',
                'widget_trainer_performance' => 'widgets',

                // Pages
                'page_dashboard' => 'setting',
                'page_edit_gym_profile' => 'setting',
                'page_register_gym' => 'setting',
                'page_settings' => 'setting',
                'page_reports' => 'report',
                'page_analytics' => 'general',
                'page_backup' => 'general',
                'page_system_health' => 'system',

                // System Operations
                'manage_settings' => 'setting',
                'view_analytics' => 'general',
                'export_data' => 'general',
                'import_data' => 'general',
                'manage_backups' => 'system',
                'view_system_logs' => 'system',
                'clear_cache' => 'system',
                'run_commands' => 'system',

                // Notifications & Communication
                'send_notifications' => 'communication',
                'send_bulk_emails' => 'communication',
                'manage_announcements' => 'communication',

                // Advanced Features
                'bulk_actions' => 'general',
                'advanced_search' => 'general',
                'manage_integrations' => 'system',
                'api_access' => 'system',

                // Reports & Analytics
                'view_financial_reports' => 'report',
                'view_member_reports' => 'report',
                'view_trainer_reports' => 'report',
                'view_equipment_reports' => 'report',
                'export_reports' => 'report',
            ];

            foreach ($filamentPermissions as $permissionName => $category) {
                $description = 'can_' . $permissionName;
                Permission::updateOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ], [
                    'display_name' => ucwords(str_replace(['_', '-'], ' ', $permissionName)),
                    'description' => ucwords(str_replace(['_', '-'], ' ', $description)),
                    'category' => PermissionCategory::tryFrom($category)->value ?? null,
                ]);
            }
        } catch (Exception $e) {
            Log::error($e);
            $this->command->error($e);
        }
    }
}
