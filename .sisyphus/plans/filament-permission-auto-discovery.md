# Filament Permission Auto-Discovery

## TL;DR

> **Quick Summary**: Create a `HasFilamentPermissions` marker interface with optional override methods, implement auto-discovery logic in `RolesAndPermissionsService`, and refactor the seeder + command to use a single source of truth for permission generation — eliminating the hardcoded duplication.
> 
> **Deliverables**:
> - `app/Contracts/HasFilamentPermissions.php` — marker interface
> - `config/filament-permissions.php` — panel paths, actions, and discovery config
> - Refactored `RolesAndPermissionsService` with discovery + permission generation
> - Refactored `RolesAndPermissionsSeeder` using Service
> - Refactored `SyncPermissionsCommand` using Service
> - HasFilamentPermissions implemented on all 38 Resources, 23 Custom Pages, 38 Widgets
> - PHPUnit tests for discovery logic and permission generation
> 
> **Estimated Effort**: Medium
> **Parallel Execution**: YES — 4 waves
> **Critical Path**: Task 1 (interface) → Task 3 (service) → Task 4+5 (seeder+command) → Task 6-9 (implementations) → Task 11 (verification)

---

## Context

### Original Request
Create a `HasFilamentPermissions` interface (marker + optional methods) implemented by Filament resources, pages, and widgets. Auto-discover implementing classes to generate permissions. Give full permissions to super-admin and gym_owner, default permissions to manager and receptionist. Sync via SyncPermissionsCommand. Refactor RolesAndPermissionsService as the single source of truth for seeder and command.

### Interview Summary
**Key Discussions**:
- Interface: Marker + optional methods (`getPermissionPrefix()`, `getPermissionActions()`)
- Discovery scope: Configurable per-panel via config
- Permission types: Both resource (action_resource) AND page/widget (page_X, widget_X)
- Default role permissions: Keep existing config system (config/default-roles-and-permissions.php + DB PermissionSetting)

**Research Findings**:
- 3 panels: admin (default), super-admin, staff
- 38 Resources, 23 Custom Pages, 38 Widgets across panels
- Hardcoded permission lists duplicated in seeder (lines 72-119) and command (lines 98-125)
- Existing service has `getDynamicDefaultPermissions()` and `getRoleAllowedResourcesList()`
- `DefaultPermissionResourceTrait` uses DB PermissionSetting with fallback to Service
- Filament v3, Laravel 12, PHP 8.2, Spatie Permission

### Gap Analysis (Self-Consultation)
**Identified Gaps** (addressed):
- **Cluster Resources**: Some resources live inside Clusters (e.g., `Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource`). Discovery must handle nested paths.
- **Base Resources**: `BaseAttendanceResource` and `BaseEmployeeResource` are abstract — should NOT generate permissions. Discovery must skip abstract classes.
- **Staff Panel**: Staff panel resources are minimal (only Dashboard page). Discovery should handle this gracefully.
- **Backward Compatibility**: Existing permission names must NOT change. Auto-derived names must match current naming convention.
- **PermissionCategory enum**: May need new entries for discovered page/widget categories.

---

## Work Objectives

### Core Objective
Replace hardcoded permission lists with auto-discovery via `HasFilamentPermissions` interface, making `RolesAndPermissionsService` the single source of truth.

### Concrete Deliverables
- `app/Contracts/HasFilamentPermissions.php`
- `config/filament-permissions.php`
- Refactored `app/Services/RolesAndPermissionsService.php`
- Refactored `database/seeders/RolesAndPermissionsSeeder.php`
- Refactored `app/Console/Commands/SyncPermissionsCommand.php`
- HasFilamentPermissions on all qualifying Filament classes
- PHPUnit tests for discovery and permission generation

### Definition of Done
- [ ] `php artisan permissions:sync` discovers all implementing classes and creates correct permissions
- [ ] Seeder produces identical permission set as before (backward compatible)
- [ ] super_admin and gym_owner get ALL discovered permissions
- [ ] manager and receptionist get default permissions from config
- [ ] Adding a new Resource with HasFilamentPermissions auto-creates its permissions on next sync
- [ ] All existing tests pass: `php artisan test --compact`

### Must Have
- HasFilamentPermissions interface with optional `getPermissionPrefix()` and `getPermissionActions()`
- Configurable panel discovery (config file)
- Auto-derive permission names from class names (backward compatible)
- Skip abstract classes during discovery
- Service as single source of truth (no duplication)
- Full permissions for super-admin and gym_owner
- Default permissions for manager and receptionist from config

### Must NOT Have (Guardrails)
- **No permission name changes** — existing names must remain identical
- **No hardcoded permission lists** in seeder or command after refactor
- **No interface on abstract base classes** (BaseAttendanceResource, BaseEmployeeResource, BasePermissionsPage)
- **No changes to Filament panel providers** or middleware
- **No changes to how roles are assigned to users**
- **No new database migrations** — use existing Spatie Permission tables
- **No over-abstraction** — keep discovery logic in Service, not a separate class

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed. No exceptions.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: YES (tests after implementation)
- **Framework**: PHPUnit
- **Test commands**: `php artisan test --compact --filter=TestName`

### QA Policy
Every task MUST include agent-executed QA scenarios.
Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **Backend**: Use Bash (php artisan) — run commands, assert output, check DB
- **Service**: Use Bash (php artisan tinker) — test service methods directly

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Start Immediately — foundation):
├── Task 1: Create HasFilamentPermissions interface [quick]
├── Task 2: Create filament-permissions config [quick]
└── Task 3: Add discovery method to RolesAndPermissionsService [deep]

Wave 2 (After Wave 1 — refactor consumers):
├── Task 4: Refactor RolesAndPermissionsSeeder (depends: 1,2,3) [unspecified-high]
└── Task 5: Refactor SyncPermissionsCommand (depends: 1,2,3) [unspecified-high]

Wave 3 (After Wave 1 — implement interface on classes, MAX PARALLEL):
├── Task 6: Implement on Admin Resources (depends: 1) [unspecified-high]
├── Task 7: Implement on SuperAdmin Resources (depends: 1) [unspecified-high]
├── Task 8: Implement on Custom Pages (depends: 1) [unspecified-high]
└── Task 9: Implement on Widgets (depends: 1) [unspecified-high]

Wave 4 (After ALL tasks — tests + verification):
├── Task 10: Write PHPUnit tests (depends: 3,4,5) [unspecified-high]
└── Task 11: Final verification (depends: ALL) [deep]

Critical Path: Task 1 → Task 3 → Task 4 → Task 6-9 → Task 11
Parallel Speedup: ~60% faster than sequential
Max Concurrent: 4 (Wave 3)
```

### Dependency Matrix

| Task | Depends On | Blocks |
|------|-----------|--------|
| 1 | — | 3, 4, 5, 6, 7, 8, 9 |
| 2 | — | 3, 4, 5 |
| 3 | 1, 2 | 4, 5, 10 |
| 4 | 1, 2, 3 | 10, 11 |
| 5 | 1, 2, 3 | 10, 11 |
| 6 | 1 | 11 |
| 7 | 1 | 11 |
| 8 | 1 | 11 |
| 9 | 1 | 11 |
| 10 | 3, 4, 5 | 11 |
| 11 | ALL | — |

### Agent Dispatch Summary

- **Wave 1**: 3 tasks — T1 → `quick`, T2 → `quick`, T3 → `deep`
- **Wave 2**: 2 tasks — T4 → `unspecified-high`, T5 → `unspecified-high`
- **Wave 3**: 4 tasks — T6 → `unspecified-high`, T7 → `unspecified-high`, T8 → `unspecified-high`, T9 → `unspecified-high`
- **Wave 4**: 2 tasks — T10 → `unspecified-high`, T11 → `deep`

---

## TODOs

- [ ] 1. Create HasFilamentPermissions Interface

  **What to do**:
  - Create `app/Contracts/HasFilamentPermissions.php`
  - Define a marker interface with two optional methods:
    - `getPermissionPrefix(): ?string` — override the auto-derived resource slug (e.g., return 'gym_member' instead of auto-deriving from class name)
    - `getPermissionActions(): ?array` — override the default action list (e.g., return ['view_any', 'view', 'create', 'update'] to exclude delete)
  - Methods return `null` by default (when not implemented by the class)
  - Add PHPDoc explaining the interface's purpose and usage
  - Place in `app/Contracts/` to follow existing pattern (`app/Contracts/GymUser.php`)

  **Must NOT do**:
  - Do NOT add any logic/methods beyond the two optional ones
  - Do NOT create a base class — this is an interface only
  - Do NOT add it to any existing classes yet (that's Tasks 6-9)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`, `laravel-best-practices`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3)
  - **Blocks**: Tasks 3, 4, 5, 6, 7, 8, 9
  - **Blocked By**: None

  **References**:
  - `app/Contracts/GymUser.php` — existing interface pattern in the project
  - `app/Interfaces/ShareableReport.php` — another interface example

  **Acceptance Criteria**:
  - [ ] File created at `app/Contracts/HasFilamentPermissions.php`
  - [ ] Interface has no required methods (marker only)
  - [ ] Two optional methods: `getPermissionPrefix(): ?string` and `getPermissionActions(): ?array`
  - [ ] PHPDoc block explains usage
  - [ ] Namespace is `App\Contracts`

  **QA Scenarios**:
  ```
  Scenario: Interface file exists and is valid PHP
    Tool: Bash
    Steps:
      1. Run `php -l app/Contracts/HasFilamentPermissions.php`
      2. Assert exit code 0 (no syntax errors)
    Expected Result: No syntax errors
    Evidence: .sisyphus/evidence/task-1-interface-syntax.txt

  Scenario: Interface can be implemented by a test class
    Tool: Bash
    Steps:
      1. Run `php artisan tinker --execute="echo interface_exists('App\Contracts\HasFilamentPermissions') ? 'EXISTS' : 'MISSING';"`
      2. Assert output contains "EXISTS"
    Expected Result: Interface is autoloaded and exists
    Evidence: .sisyphus/evidence/task-1-interface-exists.txt
  ```

  **Commit**: NO

- [ ] 2. Create filament-permissions Config

  **What to do**:
  - Create `config/filament-permissions.php` with:
    - `panels` — array of panel IDs to scan (default: `['admin', 'super-admin']`, staff excluded since it's minimal)
    - `panel_paths` — map panel IDs to their directory paths under `app/Filament/`
    - `resource_actions` — default action list for resources: `['view_any', 'view', 'create', 'update', 'delete', 'delete_any', 'force_delete', 'force_delete_any', 'restore', 'replicate', 'reorder', 'export', 'import', 'approve']`
    - `page_prefix` — permission prefix for pages: `'page_'`
    - `widget_prefix` — permission prefix for widgets: `'widget_'`
    - `guard_name` — default guard: `'web'`
    - `full_access_roles` — roles that get ALL permissions: `['super_admin', 'gym_owner']`
  - Use `config()` helper for all values (Laravel convention: never use `env()` outside .env)

  **Must NOT do**:
  - Do NOT hardcode panel paths in the Service — always read from config
  - Do NOT include staff panel by default (it's minimal and tenant-scoped)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`, `laravel-best-practices`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3)
  - **Blocks**: Tasks 3, 4, 5
  - **Blocked By**: None

  **References**:
  - `app/Providers/Filament/AdminPanelProvider.php` — panel ID is `'admin'`, path is `'admin'`
  - `app/Providers/Filament/SuperAdminPanelProvider.php` — panel ID is `'super-admin'`, path is `'super-admin'`
  - `app/Providers/Filament/StaffPanelProvider.php` — panel ID is `'staff'`, path is `'staff'`
  - `config/default-roles-and-permissions.php` — existing config pattern for role-resource mapping

  **Acceptance Criteria**:
  - [ ] File created at `config/filament-permissions.php`
  - [ ] Contains `panels`, `panel_paths`, `resource_actions`, `page_prefix`, `widget_prefix`, `guard_name`, `full_access_roles`
  - [ ] Panel paths correctly map admin → `app/Filament/Admin`, super-admin → `app/Filament/SuperAdmin`
  - [ ] All values accessible via `config('filament-permissions.key')`

  **QA Scenarios**:
  ```
  Scenario: Config file is valid PHP and returns expected structure
    Tool: Bash
    Steps:
      1. Run `php artisan tinker --execute="print_r(array_keys(config('filament-permissions')));"`
      2. Assert output contains panels, panel_paths, resource_actions
    Expected Result: Config returns expected keys
    Evidence: .sisyphus/evidence/task-2-config-structure.txt

  Scenario: Config values are correct
    Tool: Bash
    Steps:
      1. Run `php artisan tinker --execute="echo config('filament-permissions.guard_name');"`
      2. Assert output is "web"
    Expected Result: guard_name is 'web'
    Evidence: .sisyphus/evidence/task-2-config-values.txt
  ```

  **Commit**: NO

- [ ] 3. Add Discovery Method to RolesAndPermissionsService

  **What to do**:
  - Add `discoverFilamentPermissions(): array` method to `RolesAndPermissionsService`
    - Reads `config('filament-permissions.panel_paths')` to get directories to scan
    - Uses `RecursiveDirectoryIterator` + `RecursiveIteratorIterator` to scan for PHP files
    - Uses `ReflectionClass` to check if class implements `HasFilamentPermissions`
    - Skips abstract classes (`$reflection->isAbstract()`)
    - Skips interface itself
    - For Resources: derives permission prefix from class name (e.g., `GymMemberResource` → `gym_member`), generates `action_resource` permissions
    - For Pages: derives `page_{slug}` permission
    - For Widgets: derives `widget_{slug}` permission
    - Calls `getPermissionPrefix()` and `getPermissionActions()` if implemented (optional overrides)
  - Add `generateResourcePermissions(string $prefix, ?array $actions): array` helper
  - Add `generatePagePermission(string $className): string` helper
  - Add `generateWidgetPermission(string $className): string` helper
  - Add `deriveSlugFromClassName(string $className): string` helper — strips 'Resource', 'Page', 'Widget' suffixes, converts PascalCase to snake_case
  - **Refactor** `getDynamicDefaultPermissions()` to use the new discovery for building permission lists (remove hardcoded resource list from this method)
  - **Refactor** `getRoleSpecificPermissions()` to also use discovery for page/widget permissions
  - Keep `getRoleAllowedResourcesList()` and existing config-based system intact (backward compatible)
  - Remove the hardcoded `$resources` array from `createAllPermissions()` logic — replace with discovered list
  - Ensure `createDefaultTenantRolesAndAssignPermissions()` still works with the new discovery

  **Must NOT do**:
  - Do NOT create a separate `PermissionDiscoveryService` class — keep in existing Service
  - Do NOT change existing method signatures that are used by other code
  - Do NOT remove `DefaultPermissionResourceTrait` usage — it handles DB-backed PermissionSetting
  - Do NOT change how permissions are stored in the database

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Skills**: [`laravel-specialist`, `laravel-best-practices`, `phpunit-best-practices`]

  **Parallelization**:
  - **Can Run In Parallel**: YES (with care — depends on Tasks 1, 2 completing)
  - **Parallel Group**: Wave 1 (after Tasks 1, 2 start)
  - **Blocks**: Tasks 4, 5, 10
  - **Blocked By**: Tasks 1, 2

  **References**:
  - `app/Services/RolesAndPermissionsService.php` — current service to refactor (READ FULLY)
  - `app/Traits/DefaultPermissionResourceTrait.php` — must remain compatible
  - `config/default-roles-and-permissions.php` — role-to-resource mapping (keep intact)
  - `config/filament-permissions.php` — new config (from Task 2)
  - `app/Contracts/HasFilamentPermissions.php` — new interface (from Task 1)
  - `app/Filament/Admin/Resources/GymMemberResource.php` — example Resource for naming derivation
  - `app/Filament/Admin/Widgets/GymRevenueChart.php` — example Widget for naming
  - `app/Filament/Admin/Pages/QRCodeManager.php` — example custom Page for naming

  **Acceptance Criteria**:
  - [ ] `discoverFilamentPermissions()` returns array of permission names
  - [ ] Discovery correctly finds classes implementing HasFilamentPermissions
  - [ ] Abstract classes are skipped
  - [ ] Resource permissions follow `action_resource` pattern (backward compatible)
  - [ ] Page permissions follow `page_{slug}` pattern
  - [ ] Widget permissions follow `widget_{slug}` pattern
  - [ ] `getPermissionPrefix()` override works when implemented
  - [ ] `getPermissionActions()` override works when implemented
  - [ ] Existing `getDynamicDefaultPermissions()` still produces correct output
  - [ ] Existing `createDefaultTenantRolesAndAssignPermissions()` still works

  **QA Scenarios**:
  - [ ] `php artisan test --compact --filter=RolesAndPermissionsServiceTest` passes
  - [ ] Discovery returns correct count of permissions for admin panel
  - [ ] Discovery correctly derives `gym_member` from `GymMemberResource`
  - [ ] Discovery correctly derives `page_qr_code_manager` from `QRCodeManager`
  - [ ] Discovery correctly derives `widget_gym_revenue_chart` from `GymRevenueChart`
  - [ ] Abstract classes like `BaseAttendanceResource` are excluded

  **Commit**: NO

- [ ] 4. Refactor RolesAndPermissionsSeeder

  **What to do**:
  - **Remove** the hardcoded `$resources` array and `$filamentPermissions` array from `createAllPermissions()`
  - **Replace** with a call to `$this->rolesService->discoverFilamentPermissions()` to get all permission names
  - **Keep** the `createSuperAdminRole()` method — it assigns ALL permissions (now discovered)
  - **Keep** the `createPerGymRoles()` method — it uses `getDynamicDefaultPermissions()` which is already in the Service
  - **Simplify** `createAllPermissions()` to just create permissions from the discovered list
  - Ensure backward compatibility: the same permission names must be created as before
  - Add a `$this->info()` line showing how many permissions were discovered
  - Keep the `displaySummary()` method intact

  **Must NOT do**:
  - Do NOT change the permission creation logic (firstOrCreate pattern)
  - Do NOT change how super_admin or per-gym roles are created
  - Do NOT change the DB table structure or pivot inserts
  - Do NOT remove the PermissionRegistrar cache clearing

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`, `laravel-best-practices`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Task 5)
  - **Blocks**: Tasks 10, 11
  - **Blocked By**: Tasks 1, 2, 3

  **References**:
  - `database/seeders/RolesAndPermissionsSeeder.php` — current seeder to refactor (READ FULLY, especially lines 67-121)
  - `app/Services/RolesAndPermissionsService.php` — refactored service with discovery (from Task 3)
  - `config/filament-permissions.php` — config for discovery (from Task 2)

  **Acceptance Criteria**:
  - [ ] No hardcoded resource or filament permission arrays in seeder
  - [ ] `createAllPermissions()` calls Service discovery
  - [ ] Seeder produces same permission set as before (backward compatible)
  - [ ] `php artisan db:seed --class=RolesAndPermissionsSeeder` runs without errors (on test DB)
  - [ ] super_admin gets ALL discovered permissions
  - [ ] Per-gym roles get their configured defaults

  **QA Scenarios**:
  - [ ] Seeder runs on test DB: `docker-compose exec app php artisan migrate:fresh --env=testing --seed`
  - [ ] Permission count matches expected (discovered list)
  - [ ] super_admin has all permissions: `php artisan tinker --execute="echo Spatie\Permission\Models\Role::where('name','super_admin')->first()->permissions->count();"`

  **Commit**: NO

- [ ] 5. Refactor SyncPermissionsCommand

  **What to do**:
  - **Remove** the hardcoded `$resources` array, `$actions` array, and `$filamentPermissions` array from `createMissingPermissions()`
  - **Replace** with a call to `$this->rolesService->discoverFilamentPermissions()` to get all expected permission names
  - **Keep** the `syncSuperAdminPermissions()` method — it syncs ALL permissions
  - **Keep** the `syncGymOwnerPermissions()` method — it syncs ALL permissions
  - **Keep** the `syncOtherTenantRoles()` method — it uses `getDynamicDefaultPermissions()`
  - **Simplify** `createMissingPermissions()` to just diff discovered vs existing
  - Ensure `--dry-run` still works correctly
  - Ensure `--force` still works correctly
  - Add discovered permission count to output

  **Must NOT do**:
  - Do NOT change the command signature or options
  - Do NOT change how permissions are synced (insertOrIgnore pattern)
  - Do NOT change the cache clearing logic
  - Do NOT change the summary output format

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`, `laravel-best-practices`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Task 4)
  - **Blocks**: Tasks 10, 11
  - **Blocked By**: Tasks 1, 2, 3

  **References**:
  - `app/Console/Commands/SyncPermissionsCommand.php` — current command to refactor (READ FULLY, especially lines 93-171)
  - `app/Services/RolesAndPermissionsService.php` — refactored service (from Task 3)

  **Acceptance Criteria**:
  - [ ] No hardcoded permission arrays in command
  - [ ] `createMissingPermissions()` uses Service discovery
  - [ ] `--dry-run` shows discovered permissions without creating them
  - [ ] `--force` re-syncs all permissions
  - [ ] `--gym=X` limits sync to specific gym
  - [ ] Command output shows discovered permission count

  **QA Scenarios**:
  - [ ] `php artisan permissions:sync --dry-run` runs without errors
  - [ ] Dry run output shows expected permission names
  - [ ] `php artisan permissions:sync` creates missing permissions

  **Commit**: NO

- [ ] 6. Implement HasFilamentPermissions on Admin Resources

  **What to do**:
  - Add `implements HasFilamentPermissions` to each concrete Admin Resource class
  - Add `use App\Contracts\HasFilamentPermissions;` import
  - Resources to implement on (16 concrete + 2 cluster resources = 18 total):
    - `ManagerResource`, `ReceptionistResource`, `TrainerResource`, `GymMemberResource`, `MembershipPlanResource`, `GymResource`, `LeadResource`, `ExpenseResource`, `ExpenseCategoryResource`, `ExpenseBudgetResource`, `WhatsAppTemplateResource`, `WhatsAppTransactionResource`, `WhatsAppUsageLogResource`, `TenantSocialConfigResource`, `BiometricDeviceResource`, `ReportGoalResource`
    - Cluster resources: `GymMemberAttendanceResource`, `TrainerAttendanceResource`, `ScheduledReportResource`
  - **DO NOT** implement on `BaseEmployeeResource` (abstract)
  - Most resources need NO custom methods — just the marker interface
  - For resources that need different actions (e.g., no delete), implement `getPermissionActions()`
  - Permission prefix is auto-derived from class name — no need to override unless the auto-derivation is wrong

  **Must NOT do**:
  - Do NOT implement on abstract classes (`BaseEmployeeResource`)
  - Do NOT change any resource logic, forms, tables, or actions
  - Do NOT add any methods beyond what the interface requires
  - Do NOT change the `getModel()` return values

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 7, 8, 9)
  - **Blocks**: Task 11
  - **Blocked By**: Task 1

  **References**:
  - `app/Contracts/HasFilamentPermissions.php` — interface to implement (from Task 1)
  - `app/Filament/Admin/Resources/` — directory with all Admin resources
  - `app/Filament/Admin/Clusters/Attendances/Resources/` — cluster resources
  - `app/Filament/Admin/Clusters/Reports/Resources/` — cluster resources
  - `app/Filament/BaseResources/BaseEmployeeResource.php` — abstract, DO NOT implement

  **Acceptance Criteria**:
  - [ ] All 18 concrete Admin resources implement HasFilamentPermissions
  - [ ] No abstract classes implement the interface
  - [ ] Each file has correct import statement
  - [ ] No logic changes in any resource

  **QA Scenarios**:
  - [ ] `php artisan test --compact` passes (no regressions)
  - [ ] `grep -r "HasFilamentPermissions" app/Filament/Admin/Resources/` shows all 18 files

  **Commit**: NO

- [ ] 7. Implement HasFilamentPermissions on SuperAdmin Resources

  **What to do**:
  - Add `implements HasFilamentPermissions` to each concrete SuperAdmin Resource class
  - Add `use App\Contracts\HasFilamentPermissions;` import
  - Resources to implement on (11 + 4 cluster = 15 total):
    - `UserResource`, `PlanResource`, `CityResource`, `StateResource`, `CountryResource`, `BlogResource`, `CmsPageResource`, `FeedbackResource`, `ErrorReportResource`, `RequestContactResource`, `WhatsAppTemplateResource`, `WhatsAppPackageResource`, `WhatsAppAccountResource`
    - Cluster resources: `RoleResource`, `PermissionsResource`, `PermissionSettingResource`, `BusinessTypeResource`
  - Most need NO custom methods — just the marker
  - `LoginActivityResource` — check if it's a resource or widget, implement accordingly

  **Must NOT do**:
  - Do NOT change any resource logic
  - Do NOT add methods beyond interface requirements

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 6, 8, 9)
  - **Blocks**: Task 11
  - **Blocked By**: Task 1

  **References**:
  - `app/Contracts/HasFilamentPermissions.php` — interface (from Task 1)
  - `app/Filament/SuperAdmin/Resources/` — all SuperAdmin resources
  - `app/Filament/SuperAdmin/Clusters/` — cluster resources

  **Acceptance Criteria**:
  - [ ] All 15 concrete SuperAdmin resources implement HasFilamentPermissions
  - [ ] Each file has correct import statement
  - [ ] No logic changes

  **QA Scenarios**:
  - [ ] `php artisan test --compact` passes
  - [ ] `grep -r "HasFilamentPermissions" app/Filament/SuperAdmin/` shows all files

  **Commit**: NO

- [ ] 8. Implement HasFilamentPermissions on Custom Pages

  **What to do**:
  - Add `implements HasFilamentPermissions` to each concrete custom Page class
  - Add `use App\Contracts\HasFilamentPermissions;` import
  - Pages to implement on (approximately 17 concrete pages):
    - Admin standalone: `WhatsAppSetup`, `QRCodeManager`, `GymSettings`, `ExpenseReports`, `WhatsappDashboard`, `PurchaseCredits`, `Broadcast`, `EnrollMember`
    - Admin Cluster Reports: `ReportsDashboard`, `RevenueReports`, `MemberReports`, `TrainerReports`, `LeadReports`, `AttendanceReports`, `ExpenseReportsCluster`, `MemberLifetimeValueReports`, `EquipmentUsageReports`
    - SuperAdmin: `PulseDashboard`, `LoginActivity`
    - Shared: `FeedbackPage`
  - **DO NOT** implement on `BasePermissionsPage` (abstract)
  - Most pages need `getPermissionPrefix()` override since auto-derivation from page class names may not match current naming
  - Review current hardcoded `page_*` permission names to ensure backward compatibility

  **Must NOT do**:
  - Do NOT implement on abstract classes
  - Do NOT change page logic, forms, or actions
  - Do NOT change `canAccess()` methods

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 6, 7, 9)
  - **Blocks**: Task 11
  - **Blocked By**: Task 1

  **References**:
  - `app/Contracts/HasFilamentPermissions.php` — interface (from Task 1)
  - `app/Filament/Admin/Pages/` — Admin custom pages
  - `app/Filament/Admin/Clusters/Reports/Pages/` — Report pages
  - `app/Filament/BasePages/BasePermissionsPage.php` — abstract, DO NOT implement
  - Current hardcoded page permissions in seeder (lines 99-101): `page_dashboard`, `page_edit_gym_profile`, `page_register_gym`, `page_settings`, `page_reports`, `page_analytics`, `page_backup`, `page_system_health`

  **Acceptance Criteria**:
  - [ ] All concrete custom pages implement HasFilamentPermissions
  - [ ] Abstract BasePermissionsPage does NOT implement the interface
  - [ ] Permission names are backward compatible with current `page_*` names

  **QA Scenarios**:
  - [ ] `php artisan test --compact` passes
  - [ ] `grep -r "HasFilamentPermissions" app/Filament/*/Pages/ app/Filament/Pages/` shows all pages

  **Commit**: NO

- [ ] 9. Implement HasFilamentPermissions on Widgets

  **What to do**:
  - Add `implements HasFilamentPermissions` to each concrete Widget class
  - Add `use App\Contracts\HasFilamentPermissions;` import
  - Widgets to implement on (approximately 36 concrete widgets):
    - Admin Dashboard: `TodaysBirthdays`, `RecentActivity`, `GymRevenueChart`, `MembershipExpiryAlerts`, `ExpenseStatsWidget`, `RecentExpensesWidget`, `PendingExpenseApprovalsWidget`, `DashboardMemberAttendance`, `DashboardMemberAttendanceTable`, `CRMStatsWidget`, `BudgetOverviewWidget`, `RolePermissionsStatsWidget`
    - Admin Cluster Reports: 17 chart widgets
    - Admin Cluster Attendances: `TrainerAttendanceStats`, `MemberAttendanceStats`
    - Admin Pages ExpenseReports: `ExpensePaymentMethodChart`, `ExpenseMonthlyTrendChart`, `ExpenseCategoryChart`
    - SuperAdmin: `LoginActivityStatsWidget`
    - Resource-specific: `MemberProgressChart`
  - Most widgets need `getPermissionPrefix()` override since auto-derivation may not match current `widget_*` naming
  - Review current hardcoded widget permission names for backward compatibility

  **Must NOT do**:
  - Do NOT change widget logic, data fetching, or chart configurations
  - Do NOT change polling intervals or heading strings

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 6, 7, 8)
  - **Blocks**: Task 11
  - **Blocked By**: Task 1

  **References**:
  - `app/Contracts/HasFilamentPermissions.php` — interface (from Task 1)
  - `app/Filament/Admin/Widgets/` — Admin dashboard widgets
  - `app/Filament/Admin/Clusters/Reports/Widgets/` — Report chart widgets
  - `app/Filament/Admin/Clusters/Attendances/Resources/*/Widgets/` — Attendance widgets
  - Current hardcoded widget permissions in seeder (lines 102-107)

  **Acceptance Criteria**:
  - [ ] All concrete widgets implement HasFilamentPermissions
  - [ ] Permission names are backward compatible with current `widget_*` names
  - [ ] No logic changes in any widget

  **QA Scenarios**:
  - [ ] `php artisan test --compact` passes
  - [ ] `grep -r "HasFilamentPermissions" app/Filament/*/Widgets/` shows all widgets

  **Commit**: NO

- [ ] 10. Write PHPUnit Tests for Discovery and Permission Generation

  **What to do**:
  - Create `tests/Feature/Permissions/FilamentPermissionDiscoveryTest.php`:
    - `test_discovery_finds_implementing_resources()` — asserts discovery returns expected resource permissions
    - `test_discovery_finds_implementing_pages()` — asserts discovery returns expected page permissions
    - `test_discovery_finds_implementing_widgets()` — asserts discovery returns expected widget permissions
    - `test_discovery_skips_abstract_classes()` — asserts BaseAttendanceResource etc. are excluded
    - `test_discovery_respects_permission_prefix_override()` — asserts getPermissionPrefix() works
    - `test_discovery_respects_permission_actions_override()` — asserts getPermissionActions() works
    - `test_derived_slug_from_class_name()` — asserts GymMemberResource → gym_member
    - `test_derived_slug_strips_suffixes()` — asserts Page/Widget/Resource suffixes are stripped
  - Create `tests/Feature/Permissions/SyncPermissionsCommandTest.php`:
    - `test_command_creates_missing_permissions()` — asserts new permissions are created
    - `test_command_does_not_duplicate_existing()` — asserts idempotent
    - `test_dry_run_does_not_create()` — asserts --dry-run is read-only
    - `test_gym_option_limits_sync()` — asserts --gym=X only syncs that gym
  - Create `tests/Feature/Permissions/RolesAndPermissionsSeederTest.php`:
    - `test_seeder_creates_all_discovered_permissions()` — asserts permission count matches discovery
    - `test_seeder_assigns_all_to_super_admin()` — asserts super_admin has all
    - `test_seeder_assigns_defaults_to_manager()` — asserts manager has configured defaults
    - `test_seeder_assigns_defaults_to_receptionist()` — asserts receptionist has configured defaults
  - Use `--env=testing` and test database for all tests
  - Follow existing test patterns in `tests/Feature/`

  **Must NOT do**:
  - Do NOT run tests against gym_app database (use gym_app_test)
  - Do NOT create tests that depend on specific gym data existing
  - Do NOT use Pest — use PHPUnit classes only

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`phpunit-best-practices`, `laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 4 (sequential after Wave 2+3)
  - **Blocks**: Task 11
  - **Blocked By**: Tasks 3, 4, 5

  **References**:
  - `app/Services/RolesAndPermissionsService.php` — service under test
  - `app/Console/Commands/SyncPermissionsCommand.php` — command under test
  - `database/seeders/RolesAndPermissionsSeeder.php` — seeder under test
  - `tests/TestCase.php` — base test class (has DB safety check)
  - `phpunit.xml` — test configuration (forces gym_app_test)
  - Existing test files in `tests/Feature/` for patterns

  **Acceptance Criteria**:
  - [ ] 3 test files created with all test methods listed
  - [ ] `php artisan test --compact --filter=FilamentPermissionDiscoveryTest` passes
  - [ ] `php artisan test --compact --filter=SyncPermissionsCommandTest` passes
  - [ ] `php artisan test --compact --filter=RolesAndPermissionsSeederTest` passes
  - [ ] All tests use test database (gym_app_test)

  **QA Scenarios**:
  - [ ] `docker-compose exec app php artisan test --compact --filter=FilamentPermissionDiscoveryTest` — all pass
  - [ ] `docker-compose exec app php artisan test --compact --filter=SyncPermissionsCommandTest` — all pass
  - [ ] `docker-compose exec app php artisan test --compact --filter=RolesAndPermissionsSeederTest` — all pass

  **Commit**: NO

- [ ] 11. Final Verification

  **What to do**:
  - Run full test suite: `php artisan test --compact`
  - Run Pint: `vendor/bin/pint --dirty`
  - Run SyncPermissionsCommand: `php artisan permissions:sync --dry-run`
  - Verify no hardcoded permission lists remain in seeder or command
  - Verify all Resources, Pages, Widgets implement HasFilamentPermissions
  - Verify abstract classes do NOT implement the interface
  - Verify backward compatibility: existing permission names are unchanged
  - Capture evidence of all verification steps

  **Must NOT do**:
  - Do NOT skip any verification step
  - Do NOT auto-fix issues — report them for user review

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Skills**: [`laravel-specialist`, `phpunit-best-practices`]

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 4 (final, after Task 10)
  - **Blocks**: None (final task)
  - **Blocked By**: ALL tasks

  **References**:
  - All files modified in the plan
  - `phpunit.xml` — test configuration
  - `phpstan.neon` — static analysis (if configured)

  **Acceptance Criteria**:
  - [ ] `php artisan test --compact` — all tests pass
  - [ ] `vendor/bin/pint --dirty` — no formatting issues
  - [ ] `php artisan permissions:sync --dry-run` — discovers correct permissions
  - [ ] No hardcoded permission arrays in seeder or command
  - [ ] All qualifying classes implement HasFilamentPermissions
  - [ ] No abstract classes implement the interface

  **QA Scenarios**:
  - [ ] Full test suite passes on test database
  - [ ] Dry-run shows expected permission count
  - [ ] Grep confirms no hardcoded lists remain

  **Commit**: NO (verification only, no code changes)

---

## Final Verification Wave

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists. For each "Must NOT Have": search codebase for forbidden patterns. Check evidence files exist. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `vendor/bin/pint --dirty` + `php artisan test --compact`. Review all changed files for: `as any`/`@ts-ignore`, empty catches, console.log in prod, commented-out code, unused imports. Check AI slop.
  Output: `Build [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Start from clean state. Execute EVERY QA scenario from EVERY task. Test cross-task integration. Save to `.sisyphus/evidence/final-qa/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff. Verify 1:1 — everything in spec was built, nothing beyond spec was built. Check "Must NOT do" compliance. Flag unaccounted changes.
  Output: `Tasks [N/N compliant] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Success Criteria

### Verification Commands
```bash
php artisan permissions:sync --dry-run    # Expected: shows discovered permissions
php artisan test --compact                 # Expected: all tests pass
vendor/bin/pint --dirty --format agent     # Expected: code formatted
```

### Final Checklist
- [ ] All "Must Have" present
- [ ] All "Must NOT Have" absent
- [ ] All tests pass
- [ ] Permission names are backward compatible
- [ ] No hardcoded permission lists in seeder or command
