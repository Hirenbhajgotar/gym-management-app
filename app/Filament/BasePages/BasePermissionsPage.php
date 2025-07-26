<?php

// namespace App\Filament\Admin\Clusters\Permissions\Pages;

namespace App\Filament\BasePages;

use App\Filament\Admin\Clusters\Permissions;
use App\Models\Permission;
use App\Models\User;
use App\Models\PermissionSetting;
use App\Enums\PermissionCategory;
use App\Helpers\NotificationHelper;
use App\Traits\DefaultPermissionResourceTrait;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament as FacadesFilament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Filament;
use Spatie\Permission\Models\Role;
use ValueError;

abstract class BasePermissionsPage extends Page implements HasForms
{
    use InteractsWithForms, DefaultPermissionResourceTrait;

    protected static ?string $cluster = Permissions::class;
    // protected static string $view = 'filament.admin.clusters.permissions.pages.base-permissions-page';

    public ?array $data = [];

    public $defaultResources;
    public $defaultWidgets;
    public $defaultReports;
    public $defaultOtherResource;

    // Abstract properties that child classes must define
    abstract protected function getRoleName(): string;
    abstract protected function getRoleDisplayName(): string;
    // abstract protected function getDefaultPermissions(): array;
    abstract protected function getQuickSetupPermissions(): array;

    // Override these in child classes if needed
    protected function getPageTitle(): string
    {
        return $this->getRoleDisplayName() . ' Permissions';
    }

    protected function getPageHeading(): string
    {
        return $this->getRoleDisplayName() . ' Permissions';
    }

    public function __construct()
    {
        $this->setDefaultRole($this->getRoleName());
    }

    public function mount(): void
    {
        $this->loadRolePermissions();
    }

    public function form(Form $form): Form
    {
        $permissionsByCategory = $this->getPermissionsByCategory();

        $defaultResources = $this->getDefaultResources();
        $defaultWidgets = $this->getDefaultWidgets();
        $defaultReports = $this->getDefaultReports();
        $defaultOtherResource = $this->getOtherDefaultResource();


        return $form
            ->schema([
                Tabs::make('Permissions')
                    ->tabs([
                        Tabs\Tab::make('Resources')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema($this->buildCategoryTabs($permissionsByCategory, $defaultResources)),

                        Tabs\Tab::make('Widgets')
                            ->icon('heroicon-o-chart-bar')
                            ->schema($this->buildCategoryTabs($permissionsByCategory, $defaultWidgets)),

                        Tabs\Tab::make('Reports')
                            ->icon('heroicon-o-document-chart-bar')
                            ->schema($this->buildCategoryTabs($permissionsByCategory, $defaultReports)),

                        Tabs\Tab::make('Other')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema($this->buildCategoryTabs($permissionsByCategory, $defaultOtherResource)),
                    ]),
            ])
            ->statePath('data');
    }

    private function loadRolePermissions(): void
    {
        $currentTenant = FacadesFilament::getTenant();
        $roleName = $this->getRoleName();

        // Get the role for the current gym
        $role = Role::where('name', $roleName)
            ->where('gym_id', $currentTenant?->id)
            ->first();

        if (!$role) {
            // If role doesn't exist for this gym, create it
            $role = Role::create([
                'name' => $roleName,
                'guard_name' => 'web',
                'gym_id' => $currentTenant?->id,
            ]);
        }

        $rolePermissions = $role->permissions->pluck('name')->toArray();
        $permissionsByCategory = $this->getPermissionsByCategory();
        $formData = [];

        // Get the categories that should be displayed for this role
        $defaultResources = $this->getDefaultResources();
        $defaultWidgets = $this->getDefaultWidgets();
        $defaultReports = $this->getDefaultReports();
        $defaultOtherResource = $this->getOtherDefaultResource();

        // Combine all categories
        $allCategories = array_merge($defaultResources, $defaultWidgets, $defaultReports, $defaultOtherResource);

        // Group permissions by category for form filling - only include categories that should be displayed
        foreach ($permissionsByCategory as $category => $permissions) {
            if (empty($category) || !in_array($category, $allCategories)) {
                continue;
            }

            $fieldName = $category . '_permissions';
            $categoryPermissions = array_column($permissions, 'name');

            // Find intersection of role permissions and category permissions
            $selectedPermissions = array_intersect($categoryPermissions, $rolePermissions);

            $formData[$fieldName] = array_values($selectedPermissions);
        }

        // Fill the form with the prepared data
        $this->form->fill($formData);
    }

    public function save(): void
    {
        try {
            DB::beginTransaction();

            $data = $this->form->getState();
            $currentTenant = FacadesFilament::getTenant();
            $roleName = $this->getRoleName();

            // Get or create the role for the current gym
            $role = Role::firstOrCreate([
                'name' => $roleName,
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

            // Get or create permissions
            $gymPermissions = [];
            foreach ($allPermissions as $permissionName) {
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
                $gymPermissions[] = $permission;
            }

            // Sync permissions only for this gym's role
            $role->syncPermissions($gymPermissions);

            // Update all users with this role in this gym
            $this->updateRoleUsers($role, $currentTenant);

            DB::commit();

            $this->dispatch('refreshRolePermissionsStats');

            Notification::make()
                ->title($this->getRoleDisplayName() . ' Permissions Updated')
                ->body('Permissions have been updated for the current gym.')
                ->success()
                ->send();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            NotificationHelper::unexpectedError('updating permissions', $e)->send();
        }
    }

    private function updateRoleUsers(Role $role, $tenant): void
    {
        $roleName = $this->getRoleName();

        // Get all users with this role in current gym
        $roleUsers = User::whereHas('roles', function ($query) use ($roleName, $tenant) {
            $query->where('name', $roleName)
                ->where('roles.gym_id', $tenant->id);
        })->get();

        foreach ($roleUsers as $user) {
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
                ->modalDescription('Are you sure you want to reset all ' . strtolower($this->getRoleDisplayName()) . ' permissions to default values? This action cannot be undone.')
                ->modalSubmitActionLabel('Reset Permissions')
                ->action(function () {
                    $this->resetToDefaultPermissions();
                }),

            // Action::make('quick_setup')
            //     ->label('Quick Setup')
            //     ->color('info')
            //     ->icon('heroicon-o-bolt')
            //     ->action(function () {
            //         $this->quickSetupPermissions();
            //     }),
        ];
    }

    private function resetToDefaultPermissions(): void
    {
        try {
            $currentTenant = FacadesFilament::getTenant();
            $roleName = $this->getRoleName();
            $defaultPermissions = $this->getDefaultPermissions();

            DB::beginTransaction();

            $role = Role::firstOrCreate([
                'name' => $roleName,
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
            $this->updateRoleUsers($role, $currentTenant);
            DB::commit();

            $this->loadRolePermissions();

            $this->dispatch('refreshRolePermissionsStats');

            Notification::make()
                ->title('Permissions Reset')
                ->body($this->getRoleDisplayName() . ' permissions have been reset to default settings.')
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
            $currentTenant = FacadesFilament::getTenant();
            $roleName = $this->getRoleName();
            $quickSetupPermissions = $this->getQuickSetupPermissions();

            DB::beginTransaction();

            $role = Role::firstOrCreate([
                'name' => $roleName,
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
            $this->updateRoleUsers($role, $currentTenant);
            $this->loadRolePermissions();

            DB::commit();

            Notification::make()
                ->title('Quick Setup Complete')
                ->body('Common ' . strtolower($this->getRoleDisplayName()) . ' permissions have been applied.')
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
            // You can override this in child classes to add role-specific widgets
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
                ->description('Manage ' . strtolower($categoryName) . '-related permissions for ' . strtolower($this->getRoleDisplayName()) . 's')
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
