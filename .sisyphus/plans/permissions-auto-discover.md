# Permissions Auto-Discovery Refactor

## TL;DR

> **Quick Summary**: Refactor ManagerPermissionsPage and ReceptionistPermissionsPage (and their shared parent BasePermissionsPage) from hardcoded permission category lists to dynamic auto-discovery of Resources, Pages, and Widgets via the existing HasFilamentPermissions infrastructure. The gym_owner manages permissions for manager/receptionist roles in a 3-tab UI (Resources/Pages/Widgets) with grouping by class slug.
>
> **Deliverables**:
> - Refactored `BasePermissionsPage` with dynamic 3-tab auto-discovery from admin + staff panels
> - Thin `ManagerPermissionsPage` / `ReceptionistPermissionsPage` wrappers (5-10 lines each)
> - Single shared Blade view replacing two duplicate views
> - New `PermissionDiscoveryService` or extension to `RolesAndPermissionsService` for discovery logic
> - Removal/deprecation of `DefaultPermissionResourceTrait` and `PermissionSetting` dependency
> - Comprehensive tests for the refactored pages
>
> **Estimated Effort**: Medium-Large
> **Parallel Execution**: YES — 3 waves + 1 cleanup wave + final verification
> **Critical Path**: Discovery service → BasePermissionsPage refactor → Thin wrappers → View consolidation → Cleanup → Tests

---

## Context

### Original Request
> "Refactor these permissions pages to use auto-discover resources, pages, and widgets permissions. Resource's permissions should categorised in resource name e.g. lead resource permissions must be inside of lead resource section. These two pages are for gym_owner show set permissions for managers, and receptionist. Auto discover only for admin and staff panels."

### Interview Summary
**Key Decisions**:
- **Tab layout**: 3 tabs — Resources, Pages, Widgets (replacing 4-tab layout that included Reports + Other)
- **Page/Widget grouping**: By class-derived slug name (e.g., "Dashboard Page" with `page_dashboard`, "Revenue Chart Widget" with `widget_gym_revenue_chart`)
- **Non-resource permissions**: Separate "Other Permissions" section for DB permissions not matching any discovered Filament class
- **PermissionSetting overrides**: NO — pure auto-discovery. Remove PermissionSetting dependency
- **Panel deduplication**: Admin + Staff panels share resources, deduplicate silently by class name
- **Page structure**: Thin wrappers — ManagerPermissionsPage and ReceptionistPermissionsPage become 5-10 line classes extending refactored BasePermissionsPage
- **View consolidation**: Single shared Blade view template
- **Test strategy**: Tests-after-implementation with PHPUnit

### Existing Infrastructure
- `HasFilamentPermissions` interface with `defaultRolePermissions(): array` — all BaseResource/BasePage/BaseWidget implement this
- `RolesAndPermissionsService::discoverFilamentPermissions()` — scans panels, creates DB permissions
- `RolesAndPermissionsService::getDiscoveredDefaultRoleAssignments()` — aggregates role→permission assignments
- `BaseResource` defaults: Manager gets full CRUD actions, Receptionist/Trainer get [view, view_any]
- `BasePage` defaults: Manager/Receptionist get ['page']
- `BaseWidget` defaults: Manager/Receptionist get ['widget']
- 17 admin resources, 6 admin pages, 12 admin widgets, 1 staff page all implement HasFilamentPermissions
- `PermissionCategory` enum with 33 cases — still useful for "other" section labels and icons
- Permission naming convention: `{action}_{resource_slug}`, `page_{slug}`, `widget_{slug}`

---

## Work Objectives

### Core Objective
Replace hardcoded category lists in the permission management UI with dynamic discovery from Filament classes implementing `HasFilamentPermissions`, grouped by class slug.

### Concrete Deliverables
- [ ] `app/Services/PermissionDiscoveryService.php` — new service for dynamic permission grouping
- [ ] `app/Filament/BasePages/BasePermissionsPage.php` — fully refactored (dynamic tabs, discovery-based)
- [ ] `app/Filament/Admin/Clusters/Permissions/Pages/ManagerPermissionsPage.php` — thin wrapper (~5 lines)
- [ ] `app/Filament/Admin/Clusters/Permissions/Pages/ReceptionistPermissionsPage.php` — thin wrapper (~5 lines)
- [ ] `resources/views/filament/admin/clusters/permissions/permissions-page.blade.php` — single shared view
- [ ] Deleted: `resources/views/filament/admin/clusters/permissions/pages/receptionist-permissions-page.blade.php`
- [ ] Deleted: `resources/views/filament/admin/clusters/permissions/pages/manager-permissions-page.blade.php`
- [ ] Deprecated/removed: `DefaultPermissionResourceTrait` (stop using it, delete if safe)
- [ ] Deprecated/removed: `PermissionSetting` model references (data can stay in DB)
- [ ] `tests/Feature/Permissions/AutoDiscoverPermissionsTest.php` — comprehensive feature tests

### Definition of Done
- [ ] Running `docker-compose exec app php artisan test --filter=PermissionsTest` passes with 0 failures
- [ ] Navigating to `/admin/permissions/manager` shows dynamically discovered resources grouped by slug
- [ ] Navigating to `/admin/permissions/receptionist` shows dynamically discovered resources grouped by slug
- [ ] Checking/unchecking permissions in UI and saving persists correctly
- [ ] "Reset to Default" restores permissions from `defaultRolePermissions()` on discovered classes
- [ ] Both pages show 3 tabs: Resources, Pages, Widgets
- [ ] Each tab has sections named after the class slug (e.g., "Lead Resource", "Dashboard Page")
- [ ] "Other Permissions" section shows for permissions not matching any discovered class
- [ ] `vendor/bin/pint --test` passes
- [ ] `php artisan tsc --noEmit` (if applicable) passes

### Must Have
- [ ] Auto-discover all Resources implementing HasFilamentPermissions from admin + staff panels (deduplicated)
- [ ] Auto-discover all Pages implementing HasFilamentPermissions from admin + staff panels
- [ ] Auto-discover all Widgets implementing HasFilamentPermissions from admin + staff panels
- [ ] Dynamic 3-tab UI (Resources/Pages/Widgets) built from discovered classes
- [ ] Sections grouped by class slug with derived labels (e.g., "Lead Resource", "Dashboard Page")
- [ ] Role filtering — show only permissions that `defaultRolePermissions()` grants for the current role
- [ ] "Other Permissions" catch-all section at bottom for unmatched DB permissions
- [ ] Save and reset-to-default work correctly with the new dynamic structure
- [ ] Both manager and receptionist pages work

### Must NOT Have (Guardrails)
- [ ] NO PermissionSetting DB model usage for category overrides — pure auto-discovery only
- [ ] NO hardcoded category lists — no getDefaultResources(), getDefaultWidgets(), getDefaultReports(), getOtherDefaultResource()
- [ ] NO super-admin panel scanning — only admin + staff panels
- [ ] NO permission creation or DB changes — this is a UI refactor, not a permission generation change
- [ ] NO changes to the Permission model, migration, or schema

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed. No exceptions.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit + Pest)
- **Automated tests**: YES (Tests-after implementation)
- **Framework**: PHPUnit (via `php artisan test`)
- **Pattern**: Feature tests using Livewire test helpers (`livewire()->assertSee()`, `->set()`, `->call('save')`)

### QA Policy
Every task MUST include agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **Backend/PHP**: Use Bash (docker-compose exec) — run artisan commands, phpunit tests
- **UI/Form**: Use curl or Playwright — navigate to pages, verify sections appear, check permission checkboxes
- **DB verification**: Use Boost database-query tool or tinker to verify permission state

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation — 4 parallel tasks):
├── Task 1: PermissionDiscoveryService — core discovery + grouping logic [quick]
├── Task 2: Shared Blade view — single template for both pages [quick]
├── Task 3: Test infrastructure — create test case, seed data helpers [quick]
└── Task 4: Config cleanup — audit what config files need updating [quick]

Wave 2 (Core refactoring — 4 parallel tasks):
├── Task 5: Refactor BasePermissionsPage — dynamic form schema from discovery [deep]
├── Task 6: ManagerPermissionsPage → thin wrapper [quick]
├── Task 7: ReceptionistPermissionsPage → thin wrapper [quick]
└── Task 8: Test class for auto-discovery pages [quick]

Wave 3 (Cleanup — 3 parallel tasks):
├── Task 9: Remove DefaultPermissionResourceTrait — delete or fully deprecate [quick]
├── Task 10: Remove PermissionSetting model references — clean up imports [quick]
└── Task 11: Delete redundant view files + clean up config [quick]

Wave FINAL (4 parallel reviews):
├── Task F1: Plan compliance audit (oracle)
├── Task F2: Code quality review (unspecified-high)
├── Task F3: Real manual QA — full browser + test execution (unspecified-high)
└── Task F4: Scope fidelity check (deep)
  → Present results → Get explicit user okay

Critical Path: Task 1 → Task 5 → Tasks 6-8 → Tasks 9-11 → F1-F4 → user okay
Parallel Speedup: ~60% faster than sequential
```

### Agent Dispatch Summary
- **Wave 1**: 4 agents — 4× quick
- **Wave 2**: 4 agents — 1× deep, 3× quick
- **Wave 3**: 3 agents — 3× quick
- **Final**: 4 agents — oracle, unspecified-high, unspecified-high, deep

---

## TODOs

- [ ] 1. Create PermissionDiscoveryService

  **What to do**:
  Create `app/Services/PermissionDiscoveryService.php` with these public methods:

  ```php
  // Get all discovered resources for a specific role (admin+staff panels, deduplicated)
  public function getDiscoveredResourcesForRole(DefaultRole $role): array
  // Returns: ['slug' => ['label' => 'Lead Resource', 'icon' => 'heroicon-o-...', 'permissions' => [...]]]

  // Get all discovered pages for a role
  public function getDiscoveredPagesForRole(DefaultRole $role): array
  // Returns: ['dashboard' => ['label' => 'Dashboard Page', 'permissions' => ['page_dashboard']]]

  // Get all discovered widgets for a role
  public function getDiscoveredWidgetsForRole(DefaultRole $role): array
  // Returns: ['gym_revenue_chart' => ['label' => 'Gym Revenue Chart Widget', 'permissions' => ['widget_gym_revenue_chart']]]

  // Get "other" permissions not matching any discovered class
  public function getOtherPermissions(DefaultRole $role, Collection $allRolePermissions): array
  // Returns: ['system' => ['label' => 'System', 'icon' => '...', 'permissions' => [...]]]

  // Main entry point: returns all grouped permissions for the UI
  public function getGroupedPermissions(DefaultRole $role, Collection $allRolePermissions): array
  // Returns: ['resources' => [...], 'pages' => [...], 'widgets' => [...], 'other' => [...]]

  // Get ALL default permission names for a role (from all discovered classes' defaultRolePermissions())
  public function getDefaultPermissionsForRole(DefaultRole $role, string $guard): Collection
  // Returns: Collection of Permission models that are the default for this role

  // Resolve the guard name for a given role (uses config or existing service)
  public function getGuardForRole(DefaultRole $role): string
  // Returns: 'admin', 'web', etc.
  ```

  **Logic details**:
  - Scan `Filament::getPanels()` for panel IDs `['admin', 'staff']` only
  - For each panel: get `$panel->getResources()`, `getPages()`, `getWidgets()`
  - Filter by `is_subclass_of($class, HasFilamentPermissions::class)`
  - Deduplicate by class name (use a `$seen` array)
  - For each class, call `$class::defaultRolePermissions()` to check if role is referenced
  - Derive resource slug: call `$class::getPermissionSlug()` (from BaseResource)
  - Derive page slug: call `$class::getPagePermissionSlug()` (from BasePage) or strip `page_` prefix
  - Derive widget slug: call `$class::getWidgetPermissionSlug()` (from BaseWidget) or strip `widget_` prefix
  - Derive label from slug or class basename
  - Derive icon from resource's `getNavigationIcon()` (if available)
  - For "other" permissions: iterate over `$allRolePermissions`, check if permission name matches any discovered resource/page/widget pattern. If not, group by its `category` field.
  - Use `PermissionCategory` enum for "other" section labels/icons where available
  - Load the resource actions list from `config('filament-permissions.resource_actions')` to correctly parse resource slugs from permission names

  **Reference implementations**:
  - `app/Services/RolesAndPermissionsService.php:discoverFilamentPermissions()` — existing panel scanning logic
  - `app/Services/RolesAndPermissionsService.php:getDiscoveredDefaultRoleAssignments()` — role→permission mapping
  - `app/Filament/BaseResources/BaseResource.php:getPermissionSlug()` — slug derivation
  - `app/Filament/BasePages/BasePage.php:getPagePermissionSlug()` — page slug
  - `app/Filament/BaseWidgets/BaseWidget.php:getWidgetPermissionSlug()` — widget slug
  - `app/Enums/PermissionCategory.php` — for label/icon fallback
  - `config/filament-permissions.php` — resource_actions list

  **Must NOT do**:
  - NO DB writes — read-only service
  - NO modifying existing RolesAndPermissionsService unless necessary for code sharing
  - NO super-admin panel scanning
  - NO calling discoverFilamentPermissions() (that creates DB records) — only use the panel scanning approach

  **Recommended Agent Profile**:
  - **Category**: `quick` — focused, well-scoped, single class creation
  - **Skills**: `['laravel-specialist']`
  - **Why**: Domain requires deep Filament + Laravel knowledge for panel scanning and interface detection

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3, 4)
  - **Blocks**: Tasks 5, 8 (BasePermissionsPage, tests)
  - **Blocked By**: None

  **References**:
  - `app/Filament/BaseResources/BaseResource.php` — getPermissionSlug() method signature
  - `app/Filament/BasePages/BasePage.php` — getPagePermissionSlug() method
  - `app/Filament/BaseWidgets/BaseWidget.php` — getWidgetPermissionSlug() method
  - `app/Contracts/HasFilamentPermissions.php` — defaultRolePermissions() return format
  - `app/Services/RolesAndPermissionsService.php` — existing panel scanning in discoverFilamentPermissions()
  - `app/Enums/PermissionCategory.php` — label/icon mapping for other section
  - `app/Enums/DefaultRole.php` — role enum values
  - `config/filament-permissions.php:resource_actions` — list of known resource action prefixes

  **Acceptance Criteria**:
  - [ ] `PermissionDiscoveryService` class exists with all 5 public methods
  - [ ] Method signatures match the interface described above
  - [ ] Scanning admin + staff panels returns correct class lists (deduplicated)
  - [ ] `getDiscoveredResourcesForRole(DefaultRole::MANAGER)` returns 17+ resource groups
  - [ ] `getDiscoveredResourcesForRole(DefaultRole::RECEPTIONIST)` returns receptionist-accessible resources
  - [ ] `getOtherPermissions()` correctly separates non-resource/page/widget permissions
  - [ ] No DB writes — read-only

  **QA Scenarios (MANDATORY)**:

  ```
  Scenario: Service discovers all admin resources for Manager role
    Tool: Bash (docker-compose exec)
    Preconditions: Filament panels are registered, classes implement HasFilamentPermissions
    Steps:
      1. Run: docker-compose exec app php artisan tinker --execute="dump(app(App\Services\PermissionDiscoveryService::class)->getDiscoveredResourcesForRole(App\Enums\DefaultRole::MANAGER))"
      2. Assert response contains expected resource slugs (lead, gym_member, payment, trainer, etc.)
      3. Assert each group has 'label', 'icon', 'permissions' keys
    Expected Result: At least 15 resource groups returned, each with proper structure
    Evidence: .sisyphus/evidence/task-1-discovery-manager.txt

  Scenario: Service deduplicates across admin and staff panels
    Tool: Bash (docker-compose exec)
    Preconditions: Same as above
    Steps:
      1. Run: docker-compose exec app php artisan tinker --execute="dump(count(app(App\Services\PermissionDiscoveryService::class)->getDiscoveredResourcesForRole(App\Enums\DefaultRole::MANAGER)))"
      2. Count should match unique resources, not admin+staff duplicates
    Expected Result: No duplicate class names in the result
    Evidence: .sisyphus/evidence/task-1-dedup.txt

  Scenario: Other permissions are correctly identified
    Tool: Bash (docker-compose exec)
    Preconditions: DB has some permissions with non-resource categories (report, system, communication)
    Steps:
      1. Fetch all permissions for a guard from DB
      2. Pass through getOtherPermissions()
      3. Verify non-class-mapped permissions appear in 'other' group
    Expected Result: Permissions not matching any discovered class appear in other section
    Evidence: .sisyphus/evidence/task-1-other.txt
  ```

  **Evidence to Capture**:
  - [ ] task-1-discovery-manager.txt — raw dump of discovered resources
  - [ ] task-1-dedup.txt — deduplication proof
  - [ ] task-1-other.txt — other permissions categorization

---

- [ ] 2. Create Shared Blade View

  **What to do**:
  Create `resources/views/filament/admin/clusters/permissions/permissions-page.blade.php` — a single shared view template used by both ManagerPermissionsPage and ReceptionistPermissionsPage.

  The view should:
  - Use `<x-filament-panels::page>` wrapper (same as current views)
  - Display the form with tabs (Resources/Pages/Widgets) — this is rendered by Filament's form builder automatically
  - Include a header section showing the role name (from `$this->getRoleDisplayName()`)
  - Include action buttons: Save (from form) + "Reset to Default" action
  - Show a helper text explaining what each tab contains
  - Match the existing UX of the current views

  Current view structure to follow:
  ```blade
  <x-filament-panels::page>
      <x-filament-panels::form wire:submit="save">
          {{ $this->form }}

          <x-filament-panels::form.actions
              :actions="$this->getFormActions()"
          />
      </x-filament-panels::form>

      {{ $this->resetAction }}
  </x-filament-panels::page>
  ```

  **Must NOT do**:
  - NO role-specific text — the role name comes from the page class
  - NO hardcoded tab names — tabs are built dynamically by BasePermissionsPage
  - NO duplicate view logic — this single view replaces two

  **Recommended Agent Profile**:
  - **Category**: `quick` — single file, well-known pattern
  - **Skills**: `['fluxui-development', 'tailwindcss-development']`
  - **Why**: Flux UI components for Filament page wrapper, minimal styling needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3, 4)
  - **Blocks**: Nothing (view is passive)
  - **Blocked By**: None

  **References**:
  - `resources/views/filament/admin/clusters/permissions/pages/receptionist-permissions-page.blade.php` — source template to consolidate
  - `resources/views/filament/admin/clusters/permissions/pages/manager-permissions-page.blade.php` — source template (nearly identical)
  - `app/Filament/BasePages/BasePermissionsPage.php` — the page class that uses this view

  **Acceptance Criteria**:
  - [ ] Single view file exists at `resources/views/filament/admin/clusters/permissions/permissions-page.blade.php`
  - [ ] Uses `<x-filament-panels::page>` wrapper
  - [ ] Renders form with `{{ $this->form }}`
  - [ ] Shows Save and Reset to Default actions
  - [ ] Both Manager and Receptionist pages can use this view
  - [ ] No role-specific hardcoded strings

  **QA Scenarios**:

  ```
  Scenario: View renders correctly for Manager page
    Tool: Bash (docker-compose exec)
    Preconditions: View file exists, route /admin/permissions/manager works
    Steps:
      1. Login as gym_owner
      2. Navigate to /admin/permissions/manager
      3. Verify page loads without errors
      4. Verify form with tabs appears
      5. Verify Save button is present
    Expected Result: Page renders successfully with all UI elements
    Evidence: .sisyphus/evidence/task-2-view-manager.txt

  Scenario: View renders correctly for Receptionist page
    Tool: Bash (docker-compose exec)
    Steps: Same as above but for /admin/permissions/receptionist
    Expected Result: Page renders with receptionist-specific content but same template
    Evidence: .sisyphus/evidence/task-2-view-receptionist.txt
  ```

  **Evidence to Capture**:
  - [ ] task-2-view-manager.txt — page load success
  - [ ] task-2-view-receptionist.txt — page load success

---

- [ ] 3. Create Test Infrastructure for Permissions Feature

  **What to do**:
  Create `tests/Feature/Permissions/PermissionsTestCase.php` — a base test case class that:
  - Extends `Tests\TestCase`
  - Provides common setup: create a gym_owner user, set up panel authentication
  - Provides a helper `createRoleWithPermissions(DefaultRole $role, array $permissionNames = []): SpatieRole`
  - Provides a helper `assertPermissionCheckboxVisible(string $permissionName, string $checked = null)`
  - Provides a helper `getPermissionsForRole(DefaultRole $role): Collection`
  - Seeds permissions via `RolesAndPermissionsService` if needed

  Also create a data provider or factory methods for seeding test permissions.

  **Must NOT do**:
  - NO modifying the main TestCase class
  - NO duplicating existing test infrastructure

  **Recommended Agent Profile**:
  - **Category**: `quick` — well-scoped test infrastructure
  - **Skills**: `['phpunit-best-practices', 'laravel-specialist']`
  - **Why**: PHPUnit best practices for test structure, Laravel for factory/seed patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 4)
  - **Blocks**: Task 8 (auto-discovery tests depend on this)
  - **Blocked By**: None

  **References**:
  - `tests/TestCase.php` — base class to extend, safety checks for test DB
  - `tests/Feature/ExampleTest.php` — existing test patterns
  - `database/seeders/RolesAndPermissionsSeeder.php` — seed pattern for permissions
  - `app/Services/RolesAndPermissionsService.php` — permission creation

  **Acceptance Criteria**:
  - [ ] `PermissionsTestCase.php` exists with all helper methods
  - [ ] Helper methods work correctly
  - [ ] gym_owner user can authenticate to Filament admin panel

  **QA Scenarios**:

  ```
  Scenario: Test case can create gym_owner user and authenticate
    Tool: Bash (docker-compose exec)
    Preconditions: Test infrastructure files created
    Steps:
      1. Create a quick test extending PermissionsTestCase
      2. Run: docker-compose exec app php artisan test --filter=testPermissionsSetup
      3. Assert test passes (user created, panel accessible)
    Expected Result: Test passes with 0 failures
    Evidence: .sisyphus/evidence/task-3-test-infra.txt
  ```

  **Evidence to Capture**:
  - [ ] task-3-test-infra.txt — test run output

---

- [ ] 4. Audit Config Files and Identify Cleanup Targets

  **What to do**:
  Review these config files and identify what needs updating/deleting:
  
  1. `config/default-roles-and-permissions.php` — the hardcoded role→resource mappings. These will be replaced by `defaultRolePermissions()` on discovered classes.
  2. `app/Enums/PermissionCategory.php` — still needed for "other" section labels/icons, but may need a fallback mechanism for slug→label derivation
  3. `app/Traits/DefaultPermissionResourceTrait.php` — mark for deletion (no longer used after BasePermissionsPage refactor)
  4. `app/Models/PermissionSetting.php` — mark as deprecated (no longer used)
  5. `app/Casts/DefaultResourcesCast.php` — can be removed/ignored since PermissionSetting is deprecated

  Create a brief audit output recording what specifically needs to change. The key insight: `config/default-roles-and-permissions.php` is no longer the source of truth — `HasFilamentPermissions::defaultRolePermissions()` on each class is.

  For `config/filament-permissions.php`: keep as-is since the `resource_actions` list is still needed for parsing permission names.

  **Must NOT do**:
  - NO actual deletion of files yet (that's Tasks 9-11)
  - NO changing config values — pure discovery/audit

  **Recommended Agent Profile**:
  - **Category**: `quick` — pure investigation, no code changes
  - **Skills**: `[]` — straightforward audit
  - **Why**: Simple config file reading and documentation

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 3)
  - **Blocks**: Tasks 9, 10, 11 (cleanup tasks rely on audit findings)
  - **Blocked By**: None

  **References**:
  - `config/default-roles-and-permissions.php`
  - `config/filament-permissions.php`
  - `app/Enums/PermissionCategory.php`
  - `app/Traits/DefaultPermissionResourceTrait.php`
  - `app/Models/PermissionSetting.php`
  - `app/Casts/DefaultResourcesCast.php`

  **Acceptance Criteria**:
  - [ ] Audit findings documented (can be a brief inline report in the plan)
  - [ ] Clean list of files to deprecate/remove identified
  - [ ] No confusion about what each config/file provides

  **Commit**: NO (audit only, no file changes)

---

- [ ] 5. Refactor BasePermissionsPage — Dynamic Auto-Discovery

  **What to do**:
  This is the core task. Refactor `app/Filament/BasePages/BasePermissionsPage.php` from hardcoded category lists to dynamic auto-discovery.

  **Current behavior** (to replace):
  - Uses `DefaultPermissionResourceTrait` which checks PermissionSetting DB → falls back to hardcoded service methods
  - `buildCategoryTabs()` filters by PermissionCategory enum values
  - `getFormSchema()` builds 4 tabs with hardcoded type categories

  **New behavior**:
  - Uses `PermissionDiscoveryService` to get grouped permissions
  - `getFormSchema()` builds 3 dynamic tabs: Resources, Pages, Widgets + "Other Permissions" catch-all section
  - Sections are built from discovered class data, not hardcoded category lists

  **Required changes**:

  1. **Class imports**: Remove `DefaultPermissionResourceTrait`, remove `PermissionSetting` imports, add `PermissionDiscoveryService` injection

  2. **Properties**: Add `protected PermissionDiscoveryService $discoveryService;`, `protected Collection $rolePermissions;`, `protected array $discovered = [];`, `protected string $activeTab = 'Resources';`

  3. **Constructor/boot()**: Inject `PermissionDiscoveryService` via Laravel container

  4. **getFormSchema()**: Replace 4-tab hardcoded layout with dynamic 3-tab layout:
     ```
     Tabs::make('Permissions')->tabs([
         Tab::make('Resources')->badge(count(...))->schema(fn() => $this->buildSections(..., 'resources')),
         Tab::make('Pages')->badge(count(...))->schema(fn() => $this->buildSections(..., 'pages')),
         Tab::make('Widgets')->badge(count(...))->schema(fn() => $this->buildSections(..., 'widgets')),
     ])->activeTab($this->activeTab)
     ```

  5. **loadDiscoveredPermissions()**: New method that calls `PermissionDiscoveryService::getGroupedPermissions()`, caches result

  6. **buildSections()**: New method builds Section + Checkbox components from discovered groups. Shows "Other Permissions" as first section in Resources tab when there are unmatched permissions.

  7. **loadRolePermissions()**: Refactor to load directly from Permission model, no PermissionSetting/DefaultPermissionResourceTrait dependency

  8. **save()**: Refactor to get checked permission names from form state → sync to Spatie role

  9. **resetToDefaultPermissions()**: Use `PermissionDiscoveryService` to get default permissions for role → sync

  10. **getRoleName() / getRoleDisplayName()**: Make abstract methods for child classes

  11. **Remove methods**: Delete `getPermissionsByCategory()`, `buildCategoryTabs()`, and all methods from `DefaultPermissionResourceTrait`

  **Must NOT do**:
  - NO changing how permissions are stored in the database
  - NO changing the Permission model
  - NO removing methods that other classes depend on (check with lsp_find_references first)
  - NO hardcoding role names — they come from child class overrides

  **Recommended Agent Profile**:
  - **Category**: `deep` — complex refactoring of a critical page
  - **Skills**: `['laravel-specialist', 'laravel-best-practices']`
  - **Why**: Filament form schema manipulation, Livewire lifecycle, Spatie permission sync

  **Parallelization**:
  - **Can Run In Parallel**: NO (core dependency for Tasks 6-8)
  - **Parallel Group**: Wave 2
  - **Blocks**: Tasks 6, 7, 8
  - **Blocked By**: Task 1 (PermissionDiscoveryService)

  **References**:
  - `app/Filament/BasePages/BasePermissionsPage.php` — THE FILE TO REFACTOR
  - `app/Services/PermissionDiscoveryService.php` — new service (Task 1)
  - `app/Enums/PermissionCategory.php` — for other section labels
  - `app/Enums/DefaultRole.php` — role enum
  - `config/filament-permissions.php` — resource_actions for parsing permission names

  **Acceptance Criteria**:
  - [ ] No longer imports or uses DefaultPermissionResourceTrait
  - [ ] No longer queries PermissionSetting model
  - [ ] getFormSchema() returns 3 tabs: Resources / Pages / Widgets
  - [ ] Each tab has dynamic sections from discovered classes
  - [ ] "Other Permissions" section appears for unmatched permissions
  - [ ] Save correctly syncs checked permissions to Spatie role
  - [ ] Reset to Default restores from discovered defaultRolePermissions()
  - [ ] getRoleName() and getRoleDisplayName() are abstract
  - [ ] Both Manager and Receptionist pages work correctly
  - [ ] `docker-compose exec app vendor/bin/pint --dirty --format agent` passes

  **QA Scenarios**:

  ```
  Scenario: Manager page shows 3 tabs with discovered resources
    Tool: Bash (docker-compose exec + curl)
    Preconditions: gym_owner user exists, permissions seeded
    Steps:
      1. Authenticate as gym_owner and fetch /admin/permissions/manager
      2. Verify HTTP 200 response
      3. Check response contains "Resources" tab name
      4. Check response contains "Lead Resource" section (discovered)
      5. Check response contains "Pages" and "Widgets" tabs
    Expected Result: Page loads with dynamic 3-tab layout
    Evidence: .sisyphus/evidence/task-5-page.txt

  Scenario: Save persists permission changes
    Tool: Bash (docker-compose exec + tinker)
    Preconditions: gym_owner logged in
    Steps:
      1. Form submit with specific permissions checked
      2. Verify via tinker: SpatieRole::findByName('manager')->hasPermissionTo('view_any_gym_member')
      3. Form submit with permission unchecked
      4. Verify via tinker: permission is revoked
    Expected Result: Save correctly syncs checked/unchecked permissions
    Evidence: .sisyphus/evidence/task-5-save.txt

  Scenario: Reset to Default restores defaultRolePermissions()
    Tool: Bash (docker-compose exec + tinker)
    Preconditions: Permissions modified from defaults
    Steps:
      1. Remove a default permission from manager role via tinker
      2. Trigger reset action
      3. Verify permission is restored via tinker
    Expected Result: Reset correctly restores discovered defaults
    Evidence: .sisyphus/evidence/task-5-reset.txt
  ```

  **Evidence to Capture**:
  - [ ] task-5-page.txt — page load + tab/section verification
  - [ ] task-5-save.txt — save/verify cycle
  - [ ] task-5-reset.txt — reset verification

---

- [ ] 6. Thin Wrapper: ManagerPermissionsPage

  **What to do**:
  Slim down `app/Filament/Admin/Clusters/Permissions/Pages/ManagerPermissionsPage.php` to a thin wrapper (~10-15 lines).

  Current file has hardcoded `getQuickSetupPermissions()` — remove it entirely. The class should only provide:
  - `protected static ?string $navigationIcon = 'heroicon-o-shield-check';`
  - `protected static ?string $title = 'Manager Permissions';`
  - `protected ?string $heading = 'Manager Permissions';`
  - `protected ?string $subheading = 'Configure permissions for the manager role.';`
  - `protected function getRoleName(): string` returning `DefaultRole::MANAGER->value`
  - `protected function getRoleDisplayName(): string` returning `'Manager'`

  **Must NOT do**:
  - NO adding business logic — thin wrapper only
  - NO changing cluster registration or navigation

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: `['laravel-best-practices']`
  - **Why**: Simple class reduction

  **Parallelization**: YES (Wave 2, with Tasks 7, 8)
  **Blocked By**: Task 5

  **References**:
  - Current `ManagerPermissionsPage.php` file

  **Acceptance Criteria**:
  - [ ] File is ~10-15 lines
  - [ ] No getQuickSetupPermissions()
  - [ ] getRoleName() returns `DefaultRole::MANAGER->value`
  - [ ] Navigation icon preserved

---

- [ ] 7. Thin Wrapper: ReceptionistPermissionsPage

  **What to do**:
  Same as Task 6 but for ReceptionistPermissionsPage.

  Slim down to thin wrapper:
  - `navigationIcon`, `title`, `heading`, `subheading`
  - `getRoleName()` returning `DefaultRole::RECEPTIONIST->value`
  - `getRoleDisplayName()` returning `'Receptionist'`

  **Must NOT do**: Same guardrails as Task 6

  **Recommended Agent Profile**: Same as Task 6
  **Parallelization**: YES (Wave 2, with Tasks 6, 8)
  **Blocked By**: Task 5

  **References**:
  - Current `ReceptionistPermissionsPage.php`

  **Acceptance Criteria**:
  - [ ] File is ~10-15 lines
  - [ ] No getQuickSetupPermissions()
  - [ ] getRoleName() returns `DefaultRole::RECEPTIONIST->value`

---

- [ ] 8. Feature Tests for Auto-Discovery Permission Pages

  **What to do**:
  Create `tests/Feature/Permissions/AutoDiscoverPermissionsTest.php` extending `PermissionsTestCase`.

  Tests to write (minimum):
  1. `test_manager_permissions_page_loads()` — Visit /admin/permissions/manager, assert 200, assert 3 tabs visible
  2. `test_receptionist_permissions_page_loads()` — Visit /admin/permissions/receptionist, same assertions
  3. `test_manager_page_shows_resource_sections()` — Assert "Lead Resource" section visible for manager
  4. `test_receptionist_page_hides_manager_only_sections()` — Assert receptionist page does NOT show Manager-only resources (if any)
  5. `test_save_permissions_persists_to_database()` — Check a permission, save, assert DB reflects change
  6. `test_reset_to_default_restores_permissions()` — Modify permissions, reset, assert defaults restored
  7. `test_other_permissions_section_shows_unmatched_permissions()` — Verify unmatched DB permissions appear in "Other" section
  8. `test_widgets_tab_shows_discovered_widgets()` — Verify widget sections appear
  9. `test_pages_tab_shows_discovered_pages()` — Verify page sections appear

  Use Livewire test helpers: `livewire(ManagerPermissionsPage::class)->assertSee('Resources')`

  **Must NOT do**:
  - NO modifying the main database (use test database via `--env=testing`)
  - NO hardcoding permission names that don't exist
  - NO removing or skipping existing tests

  **Recommended Agent Profile**:
  - **Category**: `quick` — well-scoped test file
  - **Skills**: `['phpunit-best-practices', 'laravel-specialist']`
  - **Why**: PHPUnit/Filament test patterns, Laravel factories

  **Parallelization**: YES (Wave 2, with Tasks 6, 7)
  **Blocked By**: Tasks 3 (test infra), 5 (BasePermissionsPage)

  **References**:
  - `tests/Feature/Permissions/PermissionsTestCase.php` — base test class (Task 3)
  - `app/Filament/Admin/Clusters/Permissions/Pages/ManagerPermissionsPage.php`
  - `app/Filament/Admin/Clusters/Permissions/Pages/ReceptionistPermissionsPage.php`
  - `tests/Feature/ExampleTest.php` — test pattern reference

  **Acceptance Criteria**:
  - [ ] 9 tests written covering all scenarios
  - [ ] `docker-compose exec app php artisan test --filter=AutoDiscoverPermissionsTest` passes with 0 failures
  - [ ] Tests use Livewire helpers, not raw HTTP
  - [ ] No hardcoded assumptions about specific permission names (use discovered data)

  **QA Scenarios**:

  ```
  Scenario: All tests pass
    Tool: Bash (docker-compose exec)
    Preconditions: Test file exists, test DB seeded
    Steps:
      1. Run: docker-compose exec app composer test:db:reset
      2. Run: docker-compose exec app php artisan test --filter=AutoDiscoverPermissionsTest --compact
      3. Verify output shows "OK (9 tests, N assertions)"
    Expected Result: All 9 tests pass
    Evidence: .sisyphus/evidence/task-8-tests-pass.txt
  ```

  **Evidence to Capture**:
  - [ ] task-8-tests-pass.txt — full test run output

---

- [ ] 9. Remove DefaultPermissionResourceTrait (Deprecate + Unlink)

  **What to do**:
  - Verify NO code imports or references `DefaultPermissionResourceTrait` anymore (after BasePermissionsPage refactor)
  - Remove the `use DefaultPermissionResourceTrait;` import from BasePermissionsPage (done in Task 5)
  - Check all other files via `lsp_find_references` or grep for `DefaultPermissionResourceTrait`
  - If safe (zero remaining references): delete the trait file at `app/Traits/DefaultPermissionResourceTrait.php`
  - If unsafe (still referenced): mark with `@deprecated` comment instead

  **Must NOT do**:
  - NO deleting if any class still uses it (check first)
  - NO causing runtime errors from broken imports

  **Recommended Agent Profile**: `quick`
  **Parallelization**: YES (Wave 3, with Tasks 10, 11)
  **Blocked By**: Task 5

  **References**:
  - `app/Traits/DefaultPermissionResourceTrait.php`
  - `app/Filament/BasePages/BasePermissionsPage.php`

  **Acceptance Criteria**:
  - [ ] No code references DefaultPermissionResourceTrait (verified via grep)
  - [ ] Trait file is deleted OR marked @deprecated

  **QA**:
  ```
  Scenario: No broken imports after removal
    Tool: Bash (docker-compose exec)
    Steps:
      1. Run: docker-compose exec app php artisan test --filter=AutoDiscoverPermissionsTest
      2. Run: docker-compose exec app php artisan route:list | head -5 (smoke test)
    Expected Result: No autoload or class resolution errors
    Evidence: .sisyphus/evidence/task-9-trait-removed.txt
  ```

---

- [ ] 10. Remove PermissionSetting Model References

  **What to do**:
  - Verify PermissionSetting model and DefaultResourcesCast are no longer imported/used
  - The model file `app/Models/PermissionSetting.php` can stay (data in DB is harmless) — just remove code references
  - Remove `use App\Models\PermissionSetting;` from any remaining files (check via grep)
  - Remove `use App\Casts\DefaultResourcesCast;` from any remaining files
  - Mark files with `@deprecated` class annotation

  **Must NOT do**:
  - NO dropping DB table — data preservation
  - NO deleting model if other code depends on it

  **Recommended Agent Profile**: `quick`
  **Parallelization**: YES (Wave 3, with Tasks 9, 11)
  **Blocked By**: Task 5

  **References**:
  - `app/Models/PermissionSetting.php`
  - `app/Casts/DefaultResourcesCast.php`

  **Acceptance Criteria**:
  - [ ] No code imports PermissionSetting anymore
  - [ ] Both files marked @deprecated

---

- [ ] 11. Delete Redundant View Files + Config Cleanup

  **What to do**:
  - Delete `resources/views/filament/admin/clusters/permissions/pages/manager-permissions-page.blade.php`
  - Delete `resources/views/filament/admin/clusters/permissions/pages/receptionist-permissions-page.blade.php`
  - Update both page classes if they reference the old view paths — they should use the new shared view
  - Update `config/default-roles-and-permissions.php` — add a comment at the top: `// NOTE: This file is no longer the source of truth. Role-permission mappings come from HasFilamentPermissions::defaultRolePermissions() on each Filament class. Keep for reference only.`
  - Run `docker-compose exec app php artisan view:clear` to clear compiled views

  **Must NOT do**:
  - NO deleting views before confirming both page classes reference the new shared view
  - NO leaving broken view references

  **Recommended Agent Profile**: `quick`
  **Parallelization**: YES (Wave 3, with Tasks 9, 10)
  **Blocked By**: Task 5

  **References**:
  - Both old view files (paths above)
  - Both page classes (view path property)
  - `config/default-roles-and-permissions.php`

  **Acceptance Criteria**:
  - [ ] Old view files deleted
  - [ ] Both pages load correctly with shared view
  - [ ] `php artisan view:clear` runs without errors
  - [ ] Config file has deprecation notice

  **QA**:
  ```
  Scenario: Both pages load with shared view
    Tool: Bash (docker-compose exec + curl)
    Steps:
      1. Clear view cache: docker-compose exec app php artisan view:clear
      2. Fetch manager page: curl admin/permissions/manager (authenticated)
      3. Verify HTTP 200
      4. Fetch receptionist page: curl admin/permissions/receptionist
      5. Verify HTTP 200
    Expected Result: Both pages render correctly with shared view
    Evidence: .sisyphus/evidence/task-11-shared-view.txt
  ```

---

## Final Verification Wave (MANDATORY — after ALL implementation tasks)

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read file, curl endpoint, run command). For each "Must NOT Have": search codebase for forbidden patterns — reject with file:line if found. Check evidence files exist in .sisyphus/evidence/. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `docker-compose exec app vendor/bin/pint --test` + `php artisan test --filter=AutoDiscoverPermissionsTest`. Review all changed files for: `as any`/`@ts-ignore`, empty catches, console.log in prod, commented-out code, unused imports. Check AI slop: excessive comments, over-abstraction, generic names (data/result/item/temp).
  Output: `Lint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high` (+ `playwright` skill if UI)
  Start from clean state. Execute EVERY QA scenario from EVERY task — follow exact steps, capture evidence. Test cross-task integration (pages loading, save working, reset working). Test edge cases: empty state, invalid input, rapid actions. Save to `.sisyphus/evidence/final-qa/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | Edge Cases [N tested] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff (git log/diff). Verify 1:1 — everything in spec was built (no missing), nothing beyond spec was built (no creep). Check "Must NOT do" compliance. Detect cross-task contamination: Task N touching Task M's files. Flag unaccounted changes.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Success Criteria

### Verification Commands
```bash
# Run all tests
docker-compose exec app composer test:db:reset
docker-compose exec app php artisan test --compact

# Run permission-specific tests
docker-compose exec app php artisan test --filter=AutoDiscoverPermissionsTest --compact

# Check code quality
docker-compose exec app vendor/bin/pint --test

# Verify page loads (after authentication)
# Navigate to /admin/permissions/manager
# Navigate to /admin/permissions/receptionist

# Verify no hardcoded category methods remain
grep -r "getDefaultResources\|getDefaultWidgets\|getDefaultReports\|getOtherDefaultResource\|getQuickSetupPermissions" app/ --include="*.php"
# Expected: no matches (or only in deprecated files)
```

### Final Checklist
- [ ] All "Must Have" present — pages load, tabs show, permissions save/reset work
- [ ] All "Must NOT Have" absent — no PermissionSetting usage, no hardcoded category lists, no super-admin panel scanning
- [ ] All tests pass — `php artisan test --filter=AutoDiscoverPermissionsTest` green
- [ ] Pint passes — `vendor/bin/pint --test` green
- [ ] Both pages (manager + receptionist) load correctly with dynamic 3-tab layout
- [ ] "Other Permissions" section shows for unmatched permissions
- [ ] Old view files deleted, old traits deprecated
- [ ] Evidence files exist in .sisyphus/evidence/


