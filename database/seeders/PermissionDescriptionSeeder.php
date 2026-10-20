<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionCategory;
use App\Models\Permission;
use App\Services\RolesAndPermissionsService;
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

                'expense' => 'Expense',
                'expense_category' => 'Expense Category',
                'expense_budget' => 'Expense Budget',
            ];

            // Read the shared action list from config rather than repeating
            // it. This seeder used to keep its own copy, which is how
            // archive_gym_member and unarchive_gym_member ended up created
            // with no display metadata while every sibling action had some —
            // they render as blank rows in the permission matrix.
            /** @var array<int, string> $resourceActions */
            $resourceActions = (array) config('filament-permissions.resource_actions', []);

            // Bespoke abilities a resource declares for itself, derived from
            // the same DeclaresPermissionActions declarations permissions:sync
            // discovers, so they are described alongside the shared list
            // rather than relying on the migration to have done it.
            /** @var array<string, array<int, string>> $bespokeActionsByResource */
            $bespokeActionsByResource = resolve(RolesAndPermissionsService::class)->bespokeActionsByResourceName();

            // An empty map means no Filament panel was booted when the seeder
            // ran — the service reads the panels' resource declarations. The
            // run then silently describes only the shared config actions, so
            // an operator whose install schedules this seeder without booting
            // a panel would never learn that archive_gym_member and its
            // siblings went un-described. Make that observable rather than
            // silent.
            if ($bespokeActionsByResource === []) {
                Log::warning('PermissionDescriptionSeeder: no bespoke resource actions were discovered; the Filament panel is likely not booted, so resource-declared abilities (e.g. archive/unarchive) will not be described by this run.');
            }

            foreach ($resources as $resourceKey => $resource) {
                foreach ([...$resourceActions, ...($bespokeActionsByResource[$resourceKey] ?? [])] as $action) {

                    $permissionName = $action . '_' . $resourceKey;
                    $displayName = ucwords(str_replace(['_', '-'], ' ', $action . '_' . $resource));
                    $description = ucwords(str_replace(['_', '-'], ' ', 'can_' . $displayName));
                    $category = PermissionCategory::tryFrom($resourceKey)->value ?? null;

                    // dd($permissionName, $displayName, $description, $category);

                    $permission = Permission::query()->updateOrCreate([
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
                Permission::query()->updateOrCreate([
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
