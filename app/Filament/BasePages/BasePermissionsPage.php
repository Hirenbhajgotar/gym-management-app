<?php

declare(strict_types=1);

namespace App\Filament\BasePages;

use App\Enums\DefaultRole;
use App\Filament\Admin\Clusters\Permissions;
use App\Models\Gym;
use App\Models\Permission;
use App\Models\User;
use App\Services\PermissionDiscoveryService;
use App\Traits\HasTenantAccess;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\ActionSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * @property Form $form
 */
abstract class BasePermissionsPage extends Page implements HasForms
{
    use HasTenantAccess, InteractsWithForms;

    protected static ?string $cluster = Permissions::class;

    protected static string $view = 'filament.admin.clusters.permissions.permissions-page';

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected ?PermissionDiscoveryService $discoveryService = null;

    protected ?Collection $rolePermissions = null;

    /** @var array{resources: array, pages: array, widgets: array, other: array} */
    protected array $discovered = [];

    abstract protected function getRoleName(): string;

    abstract protected function getRoleDisplayName(): string;

    protected function getPageTitle(): string
    {
        return $this->getRoleDisplayName() . ' Permissions';
    }

    protected function getPageHeading(): string
    {
        return $this->getRoleDisplayName() . ' Permissions';
    }

    protected function getDiscoveryService(): PermissionDiscoveryService
    {
        if ($this->discoveryService === null) {
            $this->discoveryService = resolve(PermissionDiscoveryService::class);
        }

        return $this->discoveryService;
    }

    public function mount(): void
    {
        // Set team context for Spatie Permission before any role/permission queries
        setPermissionsTeamId($this->getGymId());

        $this->loadDiscoveredPermissions();
        $this->loadRolePermissions();
    }

    public function form(Form $form): Form
    {
        $this->resolveRole();
        $roleEnum = DefaultRole::from($this->getRoleName());
        $this->getDiscoveryService();

        // Protected properties don't persist across Livewire re-renders —
        // mount() only runs on initial page load, so $this->discovered is
        // reset to [] on subsequent requests (e.g. after save/notification).
        // Re-hydrate here to prevent blank resource/page/widget tabs.
        if (blank($this->discovered)) {
            $this->loadDiscoveredPermissions();
        }

        $resourceSections = $this->buildResourceSections($roleEnum);
        $pageSections = $this->buildPageSections($roleEnum);
        $widgetSections = $this->buildWidgetSections($roleEnum);

        $resourceCount = count($resourceSections);
        // Show total permission count in badges, since pages/widgets
        // are now a single section with all permissions combined.
        $pageCount = array_reduce(
            $this->discovered['pages'] ?? [],
            fn (int $c, array $p) => $c + count($p['permissions'] ?? []),
            0,
        );
        $widgetCount = array_reduce(
            $this->discovered['widgets'] ?? [],
            fn (int $c, array $w) => $c + count($w['permissions'] ?? []),
            0,
        );

        return $form
            ->schema([
                Tabs::make('Permissions')
                    ->tabs([
                        Tab::make('Resources')
                            ->icon('heroicon-o-squares-2x2')
                            ->badge($resourceCount > 0 ? $resourceCount : null)
                            ->badgeColor('primary')
                            ->schema($resourceSections),

                        Tab::make('Pages')
                            ->icon('heroicon-o-document-text')
                            ->badge($pageCount > 0 ? $pageCount : null)
                            ->badgeColor('success')
                            ->schema($pageSections),

                        Tab::make('Widgets')
                            ->icon('heroicon-o-chart-bar')
                            ->badge($widgetCount > 0 ? $widgetCount : null)
                            ->badgeColor('warning')
                            ->schema($widgetSections),
                    ], ),
            ])
            ->statePath('data');
    }

    protected function loadDiscoveredPermissions(): void
    {
        $roleEnum = DefaultRole::from($this->getRoleName());
        $rolePermissions = $this->getRolePermissions();

        $this->discovered = $this->getDiscoveryService()->getGroupedPermissions(
            $roleEnum,
            $rolePermissions,
        );
    }

    protected function loadRolePermissions(): void
    {
        $role = $this->getOrCreateRole();
        $rolePermissions = $role->permissions->pluck('name')->toArray();
        $formData = [];

        // Aggregate page and widget groups into single entries — pages/widgets
        // have only one permission per item, so per-item sections are unnecessary.
        $aggregatedPages = $this->aggregatePermissionGroups($this->discovered['pages'] ?? []);
        $aggregatedWidgets = $this->aggregatePermissionGroups($this->discovered['widgets'] ?? []);

        $allGroups = array_merge(
            $this->discovered['resources'],
            ['page' => $aggregatedPages],
            ['widget' => $aggregatedWidgets],
            $this->discovered['other'],
        );

        $roleHasAnyPermissions = filled($rolePermissions);

        foreach ($allGroups as $groupKey => $group) {
            $fieldName = $groupKey . '_permissions';
            $groupPerms = $group['permissions'];

            if ($roleHasAnyPermissions) {
                // Role has been configured — show the saved DB state for this group
                $selectedFromDb = array_values(array_intersect($groupPerms, $rolePermissions));
                $formData[$fieldName] = $selectedFromDb;
            } elseif (isset($group['default_permissions'])) {
                // Fresh role — pre-check the default permissions for this resource
                $formData[$fieldName] = $group['default_permissions'];
            } else {
                $formData[$fieldName] = [];
            }
        }

        $this->form->fill($formData);
    }

    /**
     * Combine multiple permission groups into a single flat group.
     * Pages and widgets have only one permission each, so grouping them
     * individually in separate sections is overkill.
     *
     * @param  array  $groups  Array of groups, each with a 'permissions' array
     * @return array{permissions: string[], default_permissions?: string[]}
     */
    private function aggregatePermissionGroups(array $groups): array
    {
        $permissions = [];
        $defaults = [];

        foreach ($groups as $group) {
            $permissions = array_merge($permissions, $group['permissions'] ?? []);
            if (isset($group['default_permissions'])) {
                $defaults = array_merge($defaults, $group['default_permissions']);
            }
        }

        $result = ['permissions' => $permissions];
        if (filled($defaults)) {
            $result['default_permissions'] = $defaults;
        }

        return $result;
    }

    protected function getOrCreateRole(): Role
    {
        $roleName = $this->getRoleName();

        return Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'gym_id' => $this->getGymId()], ['name' => $roleName, 'guard_name' => 'web', 'gym_id' => $this->getGymId()]);
    }

    protected function getRolePermissions(): Collection
    {
        if ($this->rolePermissions !== null) {
            return $this->rolePermissions;
        }

        $role = $this->getOrCreateRole();
        $this->rolePermissions = $role->permissions;

        return $this->rolePermissions;
    }

    protected function resolveRole(): Role
    {
        return $this->getOrCreateRole();
    }

    /**
     * @return array<int, Section>
     */
    protected function buildResourceSections(DefaultRole $role): array
    {
        $sections = [];
        $resources = $this->discovered['resources'] ?? [];

        // if (! empty($this->discovered['other'])) {
        //     $sections[] = $this->buildOtherPermissionsSection();
        // }

        foreach ($resources as $slug => $resource) {
            $fieldName = $slug . '_permissions';
            $options = [];
            $descriptions = [];

            foreach ($resource['permissions'] as $permName) {
                $options[$permName] = ucfirst(str_replace('_', ' ', $permName));
                $descriptions[$permName] = '';
            }

            $sections[] = Section::make($resource['label'])
                ->description('Manage ' . strtolower($resource['label']) . ' permissions for ' . strtolower($this->getRoleDisplayName()) . 's')
                ->icon($resource['icon'] ?? 'heroicon-o-folder')
                ->schema([
                    CheckboxList::make($fieldName)
                        ->label($resource['label'] . ' Permissions')
                        ->options($options)
                        ->columns(2)
                        ->descriptions($descriptions)
                        ->gridDirection('row')
                        ->bulkToggleable(),
                ])
                ->collapsible()
                ->collapsed(true);
        }

        return $sections;
    }

    /**
     * @return array<int, Section>
     */
    protected function buildPageSections(DefaultRole $role): array
    {
        $pages = $this->discovered['pages'] ?? [];

        if (blank($pages)) {
            return [
                Section::make('No Pages Found')
                    ->description('No pages with permissions discovered for this role.')
                    ->icon('heroicon-o-document-text')
                    ->schema([]),
            ];
        }

        // Pages have only one permission each, so display all in a single section
        $options = [];
        $descriptions = [];

        foreach ($pages as $page) {
            foreach ($page['permissions'] as $permName) {
                $options[$permName] = substr(ucfirst(str_replace('_', ' ', $permName)), strlen(config('filament-permissions.page_prefix', 'page_')));
                $descriptions[$permName] = '';
            }
        }

        return [
            Section::make('Page Permissions')
                ->description('Manage page permissions for ' . strtolower($this->getRoleDisplayName()) . 's')
                ->icon('heroicon-o-document-text')
                ->schema([
                    CheckboxList::make('page_permissions')
                        ->label('Page Permissions')
                        ->options($options)
                        ->columns(2)
                        ->descriptions($descriptions)
                        ->gridDirection('row')
                        ->bulkToggleable(),
                ])
                ->collapsible(),
        ];
    }

    /**
     * @return array<int, Section>
     */
    protected function buildWidgetSections(DefaultRole $role): array
    {
        $widgets = $this->discovered['widgets'] ?? [];

        if (blank($widgets)) {
            return [
                Section::make('No Widgets Found')
                    ->description('No widgets with permissions discovered for this role.')
                    ->icon('heroicon-o-presentation-chart-bar')
                    ->schema([]),
            ];
        }

        // Widgets have only one permission each, so display all in a single section
        $options = [];
        $descriptions = [];

        foreach ($widgets as $widget) {
            foreach ($widget['permissions'] as $permName) {
                $options[$permName] = substr(ucfirst(str_replace('_', ' ', $permName)), strlen(config('filament-permissions.widget_prefix', 'widget_')));
                $descriptions[$permName] = '';
            }
        }

        return [
            Section::make('Widget Permissions')
                ->description('Manage widget permissions for ' . strtolower($this->getRoleDisplayName()) . 's')
                ->icon('heroicon-o-presentation-chart-bar')
                ->schema([
                    CheckboxList::make('widget_permissions')
                        ->label('Widget Permissions')
                        ->options($options)
                        ->columns(2)
                        ->descriptions($descriptions)
                        ->gridDirection('row')
                        ->bulkToggleable(),
                ])
                ->collapsible(),
        ];
    }

    protected function buildOtherPermissionsSection(): Section
    {
        $otherPerms = $this->discovered['other'] ?? [];
        $fieldName = 'other_permissions';
        $options = [];
        $descriptions = [];

        foreach ($otherPerms as $group) {
            foreach ($group['permissions'] as $permName) {
                $options[$permName] = ucfirst(str_replace('_', ' ', $permName));
                $descriptions[$permName] = '';
            }
        }

        return Section::make('Other Permissions')
            ->description('Additional permissions not categorized under resources, pages, or widgets.')
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                CheckboxList::make($fieldName)
                    ->label('Other Permissions')
                    ->options($options)
                    ->columns(2)
                    ->descriptions($descriptions)
                    ->gridDirection('row')
                    ->bulkToggleable(),
            ])
            ->collapsible();
    }

    public function save(): void
    {
        try {
            // Ensure Spatie Permission knows which team we're operating on.
            // This is critical in Livewire HTTP context where test-level
            // setPermissionsTeamId() doesn't propagate across the request boundary.
            $gymId = $this->getGymId();
            setPermissionsTeamId($gymId);

            DB::beginTransaction();

            // Read form state directly from the component's data property.
            // form()->getState() can return empty in Livewire test contexts
            // because the form's internal state may not sync with $this->data.
            // $data = $this->data ?? $this->form->getState();
            $data = $this->form->getState();

            $validatedData = $this->validate();

            if (blank($data) && filled($validatedData['data'])) {
                $data = $validatedData['data'];
            }

            $role = $this->getOrCreateRole();

            // Collect all submitted permission names from the form
            $submittedPerms = [];
            foreach ($data as $permissions) {
                if (is_array($permissions)) {
                    $submittedPerms = array_merge($submittedPerms, $permissions);
                }
            }
            $submittedPerms = array_unique($submittedPerms);

            // Get current permissions for this role from the DB
            $currentPerms = $role->permissions->pluck('name')->toArray();

            // Compute delta — skip the DB write entirely if nothing changed
            $toGrant = array_diff($submittedPerms, $currentPerms);
            $toRevoke = array_diff($currentPerms, $submittedPerms);

            // dd($toGrant, $toRevoke, array_merge($toGrant, $toRevoke));

            if (filled($toGrant) || filled($toRevoke)) {
                // Batch-find all existing Permission records in a single query
                // instead of looping firstOrCreate (which does N queries for N
                // permissions — up to 455 for a full-form save).
                $existingPermissions = Permission::query()->whereIn('name', $submittedPerms)
                    ->where('guard_name', 'web')
                    ->get()
                    ->keyBy('name');

                // Determine which permissions don't exist in the DB yet
                $missingNames = array_values(array_diff(
                    $submittedPerms,
                    $existingPermissions->keys()->all(),
                ));

                // Batch-insert all missing permissions at once (1 query total)
                if (filled($missingNames)) {
                    $now = now();
                    Permission::query()->insert(array_map(fn (string $name): array => [
                        'name' => $name,
                        'guard_name' => 'web',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $missingNames));

                    // Reload the newly created records
                    $newPermissions = Permission::query()->whereIn('name', $missingNames)
                        ->where('guard_name', 'web')
                        ->get()
                        ->keyBy('name');

                    $existingPermissions = $existingPermissions->merge($newPermissions);
                }

                if (filled($toGrant)) {
                    $grantPermissions = Permission::query()->whereIn('name', $toGrant)->where('guard_name', 'web')->get();
                    $role->givePermissionTo($grantPermissions);
                }

                if (filled($toRevoke)) {
                    $revokePermissions = Permission::query()->whereIn('name', $toRevoke)->where('guard_name', 'web')->get();
                    $role->revokePermissionTo($revokePermissions);
                }

                if (filled($toGrant) || filled($toRevoke)) {
                    resolve(PermissionRegistrar::class)->forgetCachedPermissions();
                }
            }

            DB::commit();

            $this->dispatch('refreshRolePermissionsStats');

            Notification::make()
                ->title('Permissions Updated')
                ->body('Permissions have been updated successfully.')
                ->success()
                ->send();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            Notification::make()
                ->title('Error Updating Permissions')
                ->body('An error occurred while updating permissions.')
                ->danger()
                ->send();
        }
    }

    private function updateRoleUsers(Role $role, Gym $tenant): void
    {
        $roleName = $this->getRoleName();

        $roleUsers = User::query()->whereHas('roles', function (Builder $query) use ($roleName, $tenant) {
            $query->where('name', $roleName)
                ->where('roles.gym_id', $tenant->id);
        })->get();

        foreach ($roleUsers as $user) {
            $user->syncPermissions($role->permissions);
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('gym_owner') ?? false;
    }

    protected function getActions(): array
    {
        return [
            $this->getResetPermissionAction(),
        ];
    }

    public function getResetPermissionAction(): Action
    {
        return Action::make('reset_permissions')
            ->label('Reset to Default')
            ->color('gray')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->modalHeading('Reset Permissions')
            ->modalDescription('Are you sure you want to reset all ' . strtolower($this->getRoleDisplayName()) . ' permissions to default values? This action cannot be undone.')
            ->modalSubmitActionLabel('Reset Permissions')
            ->size(ActionSize::Large)
            ->action(function () {
                $this->resetToDefaultPermissions();
            });
    }

    private function resetToDefaultPermissions(): void
    {
        try {
            $roleEnum = DefaultRole::from($this->getRoleName());
            $defaultPermissions = $this->getDiscoveryService()->getDefaultPermissionsForRole($roleEnum);

            DB::beginTransaction();

            $role = $this->getOrCreateRole();

            $role->syncPermissions($defaultPermissions);
            DB::commit();

            $this->rolePermissions = null;
            $this->loadDiscoveredPermissions();
            $this->loadRolePermissions();

            $this->dispatch('refreshRolePermissionsStats');

            Notification::make()
                ->title('Permissions Reset')
                ->body('Permissions have been reset to default settings.')
                ->success()
                ->send();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            Notification::make()
                ->title('Permissions Reset Failed')
                ->body('An error occurred while resetting permissions.')
                ->danger()
                ->send();
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
