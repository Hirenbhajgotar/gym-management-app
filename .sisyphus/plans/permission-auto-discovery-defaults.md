# Auto-Discovery of Default Role Permissions

## TL;DR

> **Quick Summary**: Replace hardcoded default role-permission mappings with a declarative system where resources, pages, and widgets declare which roles should receive their permissions by default. Enhance the `HasFilamentPermissions` interface with a `getDefaultRolePermissions()` method, create abstract base classes (`BaseResource`, `BasePage`, `BaseWidget`) for centralized implementation, and update the discovery service to auto-collect these declarations.
> 
> **Deliverables**:
> - Enhanced `HasFilamentPermissions` interface with `getDefaultRolePermissions(): array`
> - New `BaseResource` abstract class in `app/Filament/BaseResources/`
> - New `BasePage` abstract class in `app/Filament/BasePages/`
> - New `BaseWidget` abstract class in `app/Filament/BaseWidgets/`
> - Updated `RolesAndPermissionsService` with discovery logic
> - Updated seeder and sync command to use discovered defaults
> - Configuration fallback preserved for backward compatibility
> 
> **Estimated Effort**: Medium
> **Parallel Execution**: YES - 3 waves
> **Critical Path**: Interface → Base Classes → Service → Seeder/Command

---

## Context

### Original Request
Create a comprehensive plan to auto-discover resources and custom pages for default permissions. Instead of setting default resources only in `config/default-roles-and-permissions.php`, make it auto-discoverable. If there's a flag in a resource or custom page, make it default permissions for roles. Create a method inside `HasFilamentPermissions` and implement it in resources and pages. Create a base abstract class `BaseResource` that implements `HasFilamentPermissions`, allowing easy override of permissions. Also create `BasePage` and `BaseWidget` abstract classes for pages and widgets respectively.

### Interview Summary
**Key Discussions**:
- Keep `config/default-roles-and-permissions.php` as fallback for safety
- BaseResource placed in `app/Filament/BaseResources/` directory
- BasePage placed in `app/Filament/BasePages/` directory
- BaseWidget placed in `app/Filament/BaseWidgets/` directory
- Use `DefaultRole` enum values for type-safe return types
- Add `getDefaultRolePermissions()` method to base classes only; existing 79 classes add it incrementally

**Research Findings**:
- 79 classes already implement `HasFilamentPermissions` (currently empty marker interface)
- 3 existing abstract base classes: `BaseAttendanceResource`, `BaseEmployeeResource`, `BaseResources/BaseAttendanceResource`
- `discoverFilamentPermissions()` already scans for `HasFilamentPermissions` implementations
- Current default permissions are hardcoded in `config/default-roles-and-permissions.php`
- `getDynamicDefaultPermissions()` generates permissions from allowed resources list

### Metis Review
**Identified Gaps** (addressed):
- Need to ensure backward compatibility when adding method to interface
- Should use PHP 8.0+ interface default methods or abstract class pattern
- Discovery service needs to handle resources without the new method gracefully

---

## Work Objectives

### Core Objective
Transform the permission system from hardcoded config-based defaults to a declarative, auto-discovered system where resources/pages declare their own default role assignments.

### Concrete Deliverables
- `app/Contracts/HasFilamentPermissions.php` - Enhanced interface with `getDefaultRolePermissions()` method
- `app/Filament/BaseResources/BaseResource.php` - Abstract base class for Resources
- `app/Filament/BasePages/BasePage.php` - Abstract base class for Pages
- `app/Filament/BaseWidgets/BaseWidget.php` - Abstract base class for Widgets
- `app/Services/RolesAndPermissionsService.php` - Updated with discovery logic for default roles
- `database/seeders/RolesAndPermissionsSeeder.php` - Updated to use discovered defaults
- `app/Console/Commands/SyncPermissionsCommand.php` - Updated to sync discovered defaults

### Definition of Done
- [ ] `HasFilamentPermissions` interface has `getDefaultRolePermissions(): array` method
- [ ] `BaseResource` abstract class implements the interface with sensible defaults
- [ ] `BasePage` abstract class implements the interface with sensible defaults
- [ ] `BaseWidget` abstract class implements the interface with sensible defaults
- [ ] `RolesAndPermissionsService::discoverFilamentPermissions()` collects default role assignments
- [ ] Seeder uses discovered defaults instead of hardcoded config
- [ ] Sync command updates role-permission mappings based on discoveries
- [ ] Existing resources/pages/widgets without the method continue to work (backward compatible)
- [ ] All existing tests pass

### Must Have
- Type-safe `DefaultRole` enum return type for `getDefaultRolePermissions()`
- Backward compatibility with existing 79 `HasFilamentPermissions` implementations
- Config fallback preserved for safety
- Easy override mechanism in child resources/pages/widgets
- Separate abstract classes for Resource, Page, and Widget

### Must NOT Have (Guardrails)
- Do NOT break existing resources that don't implement `getDefaultRolePermissions()`
- Do NOT change permission naming conventions
- Do NOT modify Spatie Permission package behavior
- Do NOT require all 79 existing classes to be updated immediately

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** - ALL verification is agent-executed. No exceptions.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: Tests-after
- **Framework**: PHPUnit

### QA Policy
Every task MUST include agent-executed QA scenarios.
Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Start Immediately - foundation):
├── Task 1: Enhance HasFilamentPermissions interface [quick]
├── Task 2: Create BaseResource abstract class [quick]
├── Task 3: Create BasePage abstract class [quick]
├── Task 4: Create BaseWidget abstract class [quick]
└── Task 5: Update filament-permissions config [quick]

Wave 2 (After Wave 1 - core logic):
├── Task 6: Update RolesAndPermissionsService discovery [deep]
├── Task 7: Update RolesAndPermissionsSeeder [unspecified-high]
└── Task 8: Update SyncPermissionsCommand [unspecified-high]

Wave FINAL (After ALL tasks - verification):
├── Task F1: Plan compliance audit [oracle]
├── Task F2: Code quality review [unspecified-high]
├── Task F3: Real manual QA [unspecified-high]
└── Task F4: Scope fidelity check [deep]
-> Present results -> Get explicit user okay

Critical Path: Task 1 → Task 6 → Task 7 → F1-F4 → user okay
Parallel Speedup: ~50% faster than sequential
Max Concurrent: 5 (Wave 1)
```

### Dependency Matrix

| Task | Depends On | Blocks |
|------|-----------|--------|
| 1 | None | 2, 3, 4, 6 |
| 2 | 1 | 6, 7, 8 |
| 3 | 1 | 6, 7, 8 |
| 4 | 1 | 6, 7, 8 |
| 5 | None | 6 |
| 6 | 1, 2, 3, 4, 5 | 7, 8 |
| 7 | 6 | F1-F4 |
| 8 | 6 | F1-F4 |

### Agent Dispatch Summary

- **Wave 1**: 5 tasks - T1 → `quick`, T2 → `quick`, T3 → `quick`, T4 → `quick`, T5 → `quick`
- **Wave 2**: 3 tasks - T6 → `deep`, T7 → `unspecified-high`, T8 → `unspecified-high`
- **FINAL**: 4 tasks - F1 → `oracle`, F2 → `unspecified-high`, F3 → `unspecified-high`, F4 → `deep`

---

## TODOs

- [ ] 1. Enhance HasFilamentPermissions Interface

  **What to do**:
  - Add `getDefaultRolePermissions(): array` method to the interface
  - Add PHPDoc describing the return type as `DefaultRole[]`
  - Ensure backward compatibility (existing implementations won't break because they're marker interface currently)

  **Must NOT do**:
  - Do NOT add any other methods to the interface yet
  - Do NOT change the interface namespace

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Interface design and PHP 8.2+ conventions

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3, 4, 5)
  - **Blocks**: Tasks 2, 3, 4, 6
  - **Blocked By**: None (can start immediately)

  **References**:
  - `app/Contracts/HasFilamentPermissions.php` - Current marker interface (22 lines)
  - `app/Enums/DefaultRole.php` - Enum to use for return type

  **Acceptance Criteria**:
  - [ ] Interface has `getDefaultRolePermissions(): array` method signature
  - [ ] PHPDoc specifies `@return DefaultRole[]`
  - [ ] File passes PHPStan level 5

  **QA Scenarios**:
  ```
  Scenario: Interface compiles without errors
    Tool: Bash (artisan tinker)
    Steps:
      1. Run `php artisan tinker --execute="echo 'Interface OK';"`
      2. Verify no syntax errors
    Expected Result: Output "Interface OK" without errors
    Evidence: .sisyphus/evidence/task-1-interface-compile.txt
  ```

  **Commit**: NO

---

- [ ] 2. Create BaseResource Abstract Class

  **What to do**:
  - Create `app/Filament/BaseResources/BaseResource.php`
  - Extend `Filament\Resources\Resource`
  - Implement `HasFilamentPermissions` interface
  - Provide default `getDefaultRolePermissions()` returning empty array
  - Add common permission helper methods:
    - `getPermissionSlug(): string` - derives slug from model
    - `canAccess(): bool` - checks user permissions
  - Add PHPDoc with usage examples

  **Must NOT do**:
  - Do NOT duplicate logic from existing `BaseAttendanceResource` or `BaseEmployeeResource`
  - Do NOT require child classes to override `getDefaultRolePermissions()` (empty is valid)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`, `filament-development`]
    - `laravel-specialist`: Abstract class patterns, PHP 8.2+
    - `filament-development`: Filament Resource conventions

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3, 4, 5)
  - **Blocks**: Tasks 6, 7, 8
  - **Blocked By**: Task 1

  **References**:
  - `app/Filament/BaseResources/BaseAttendanceResource.php` - Existing abstract pattern
  - `app/Filament/Admin/Resources/BaseEmployeeResource.php` - Another abstract pattern
  - `app/Filament/Admin/Resources/GymMemberResource.php:43` - Example implementing `HasFilamentPermissions`

  **Acceptance Criteria**:
  - [ ] File created at `app/Filament/BaseResources/BaseResource.php`
  - [ ] Extends `Filament\Resources\Resource`
  - [ ] Implements `HasFilamentPermissions`
  - [ ] Has `getDefaultRolePermissions(): array` returning `[]`
  - [ ] Has `getPermissionSlug(): string` helper
  - [ ] Has `canAccess(): bool` helper
  - [ ] File passes PHPStan level 5

  **QA Scenarios**:
  ```
  Scenario: BaseResource can be extended
    Tool: Bash (php artisan tinker)
    Steps:
      1. Create a test class extending BaseResource
      2. Verify it implements HasFilamentPermissions
      3. Verify getDefaultRolePermissions() returns empty array
    Expected Result: Test class compiles and methods work
    Evidence: .sisyphus/evidence/task-2-base-resource-test.txt
  ```

  **Commit**: NO

---

- [ ] 3. Create BasePage Abstract Class

  **What to do**:
  - Create `app/Filament/BasePages/BasePage.php`
  - Extend `Filament\Resources\Pages\Page` (or appropriate Filament page base class)
  - Implement `HasFilamentPermissions` interface
  - Provide default `getDefaultRolePermissions()` returning empty array
  - Add common permission helper methods:
    - `getPagePermissionSlug(): string` - derives slug from class name
    - `canAccess(): bool` - checks user permissions
  - Add PHPDoc with usage examples

  **Must NOT do**:
  - Do NOT duplicate logic from existing page classes
  - Do NOT require child classes to override `getDefaultRolePermissions()` (empty is valid)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`, `filament-development`]
    - `laravel-specialist`: Abstract class patterns, PHP 8.2+
    - `filament-development`: Filament Page conventions

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 4, 5)
  - **Blocks**: Tasks 6, 7, 8
  - **Blocked By**: Task 1

  **References**:
  - `app/Filament/Pages/FeedbackPage.php` - Existing page implementing `HasFilamentPermissions`
  - `app/Filament/SuperAdmin/Pages/PulseDashboard.php` - Another page example
  - `app/Filament/Admin/Pages/ExpenseReports.php` - Page with widgets

  **Acceptance Criteria**:
  - [ ] File created at `app/Filament/BasePages/BasePage.php`
  - [ ] Extends `Filament\Resources\Pages\Page`
  - [ ] Implements `HasFilamentPermissions`
  - [ ] Has `getDefaultRolePermissions(): array` returning `[]`
  - [ ] Has `getPagePermissionSlug(): string` helper
  - [ ] Has `canAccess(): bool` helper
  - [ ] File passes PHPStan level 5

  **QA Scenarios**:
  ```
  Scenario: BasePage can be extended
    Tool: Bash (php artisan tinker)
    Steps:
      1. Create a test class extending BasePage
      2. Verify it implements HasFilamentPermissions
      3. Verify getDefaultRolePermissions() returns empty array
    Expected Result: Test class compiles and methods work
    Evidence: .sisyphus/evidence/task-3-base-page-test.txt
  ```

  **Commit**: NO

---

- [ ] 4. Create BaseWidget Abstract Class

  **What to do**:
  - Create `app/Filament/BaseWidgets/BaseWidget.php`
  - Extend `Filament\Widgets\Widget`
  - Implement `HasFilamentPermissions` interface
  - Provide default `getDefaultRolePermissions()` returning empty array
  - Add common permission helper methods:
    - `getWidgetPermissionSlug(): string` - derives slug from class name
    - `canAccess(): bool` - checks user permissions
  - Add PHPDoc with usage examples

  **Must NOT do**:
  - Do NOT duplicate logic from existing widget classes
  - Do NOT require child classes to override `getDefaultRolePermissions()` (empty is valid)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`, `filament-development`]
    - `laravel-specialist`: Abstract class patterns, PHP 8.2+
    - `filament-development`: Filament Widget conventions

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 3, 5)
  - **Blocks**: Tasks 6, 7, 8
  - **Blocked By**: Task 1

  **References**:
  - `app/Filament/Admin/Widgets/GymRevenueChart.php` - Existing widget implementing `HasFilamentPermissions`
  - `app/Filament/Admin/Widgets/RolePermissionsStatsWidget.php` - Another widget example
  - `app/Filament/BaseWidgets/BaseAttendanceStats.php` - Existing base widget pattern

  **Acceptance Criteria**:
  - [ ] File created at `app/Filament/BaseWidgets/BaseWidget.php`
  - [ ] Extends `Filament\Widgets\Widget`
  - [ ] Implements `HasFilamentPermissions`
  - [ ] Has `getDefaultRolePermissions(): array` returning `[]`
  - [ ] Has `getWidgetPermissionSlug(): string` helper
  - [ ] Has `canAccess(): bool` helper
  - [ ] File passes PHPStan level 5

  **QA Scenarios**:
  ```
  Scenario: BaseWidget can be extended
    Tool: Bash (php artisan tinker)
    Steps:
      1. Create a test class extending BaseWidget
      2. Verify it implements HasFilamentPermissions
      3. Verify getDefaultRolePermissions() returns empty array
    Expected Result: Test class compiles and methods work
    Evidence: .sisyphus/evidence/task-4-base-widget-test.txt
  ```

  **Commit**: NO

---

- [ ] 5. Update filament-permissions Config

  **What to do**:
  - Add `discover_default_roles` boolean config option (default: true)
  - Add `fallback_default_roles` array config option (mirrors current hardcoded values)
  - Document the new options with PHPDoc

  **Must NOT do**:
  - Do NOT remove existing config options
  - Do NOT change existing config structure

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 3, 4)
  - **Blocks**: Task 6
  - **Blocked By**: None

  **References**:
  - `config/filament-permissions.php` - Current config structure
  - `config/default-roles-and-permissions.php` - Current hardcoded defaults

  **Acceptance Criteria**:
  - [ ] Config has `discover_default_roles` option
  - [ ] Config has `fallback_default_roles` option
  - [ ] Default values preserve current behavior
  - [ ] Config file has no syntax errors

  **QA Scenarios**:
  ```
  Scenario: Config loads correctly
    Tool: Bash (php artisan tinker)
    Steps:
      1. Run `php artisan tinker --execute="print_r(config('filament-permissions'));"`
      2. Verify new options exist
    Expected Result: Config array contains new keys
    Evidence: .sisyphus/evidence/task-5-config-test.txt
  ```

  **Commit**: NO

---

- [ ] 6. Update RolesAndPermissionsService Discovery

  **What to do**:
  - Modify `discoverFilamentPermissions()` to collect `getDefaultRolePermissions()` from each resource/page/widget
  - Add new method `getDiscoveredDefaultRoleAssignments(): array` that returns role→permissions mapping
  - Merge discovered defaults with fallback config
  - Handle classes that don't implement `getDefaultRolePermissions()` gracefully (empty array)

  **Must NOT do**:
  - Do NOT break existing `discoverFilamentPermissions()` behavior
  - Do NOT require all resources/pages/widgets to implement `getDefaultRolePermissions()`

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Skills**: [`laravel-specialist`, `laravel-security`]
    - `laravel-specialist`: Service architecture, reflection
    - `laravel-security`: Permission system patterns

  **Parallelization**:
  - **Can Run In Parallel**: NO (sequential after Wave 1)
  - **Parallel Group**: Wave 2
  - **Blocks**: Tasks 7, 8
  - **Blocked By**: Tasks 1, 2, 3, 4, 5

  **References**:
  - `app/Services/RolesAndPermissionsService.php:365-428` - Current `discoverFilamentPermissions()` method
  - `app/Services/RolesAndPermissionsService.php:145-191` - `getDynamicDefaultPermissions()` method
  - `app/Services/RolesAndPermissionsService.php:196-204` - `getRoleAllowedResourcesList()` method

  **Acceptance Criteria**:
  - [ ] `discoverFilamentPermissions()` still returns permission names
  - [ ] New `getDiscoveredDefaultRoleAssignments()` method exists
  - [ ] Method returns `array<string, string[]>` (role => permissions)
  - [ ] Falls back to config when class doesn't implement method
  - [ ] All existing tests pass

  **QA Scenarios**:
  ```
  Scenario: Discovery collects default roles
    Tool: Bash (php artisan tinker)
    Steps:
      1. Create a test resource extending BaseResource with getDefaultRolePermissions()
      2. Run discovery
      3. Verify the resource appears in discovered assignments
    Expected Result: Resource's default roles are collected
    Evidence: .sisyphus/evidence/task-6-discovery-test.txt

  Scenario: Fallback works for classes without method
    Tool: Bash (php artisan tinker)
    Steps:
      1. Existing resource without getDefaultRolePermissions()
      2. Run discovery
      3. Verify it doesn't break and uses fallback
    Expected Result: No errors, fallback config used
    Evidence: .sisyphus/evidence/task-6-fallback-test.txt
  ```

  **Commit**: NO

---

- [ ] 7. Update RolesAndPermissionsSeeder

  **What to do**:
  - Modify `createPerGymRoles()` to use discovered default role assignments
  - Merge discovered defaults with fallback config
  - Update `getDynamicDefaultPermissions()` to use new discovery method
  - Preserve existing behavior for roles not covered by discovery

  **Must NOT do**:
  - Do NOT break seeder for existing databases
  - Do NOT change permission naming conventions

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES (with Task 8)
  - **Parallel Group**: Wave 2
  - **Blocks**: F1-F4
  - **Blocked By**: Task 6

  **References**:
  - `database/seeders/RolesAndPermissionsSeeder.php:106-188` - `createPerGymRoles()` method
  - `database/seeders/RolesAndPermissionsSeeder.php:64-83` - `createAllPermissions()` method

  **Acceptance Criteria**:
  - [ ] Seeder uses discovered defaults
  - [ ] Fallback config still works
  - [ ] Existing databases can be re-seeded without errors
  - [ ] Permission count matches expected

  **QA Scenarios**:
  ```
  Scenario: Seeder runs successfully
    Tool: Bash (docker-compose exec app php artisan db:seed --class=RolesAndPermissionsSeeder --env=testing)
    Steps:
      1. Reset test database
      2. Run seeder
      3. Verify permissions created
      4. Verify roles have correct permissions
    Expected Result: Seeder completes without errors
    Evidence: .sisyphus/evidence/task-7-seeder-test.txt
  ```

  **Commit**: NO

---

- [ ] 8. Update SyncPermissionsCommand

  **What to do**:
  - Modify `syncOtherTenantRoles()` to use discovered default role assignments
  - Add option to force re-sync all role-permission mappings
  - Update display summary to show discovered vs fallback counts

  **Must NOT do**:
  - Do NOT break existing command behavior
  - Do NOT require all classes to be updated

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES (with Task 7)
  - **Parallel Group**: Wave 2
  - **Blocks**: F1-F4
  - **Blocked By**: Task 6

  **References**:
  - `app/Console/Commands/SyncPermissionsCommand.php:234-294` - `syncOtherTenantRoles()` method
  - `app/Console/Commands/SyncPermissionsCommand.php:80-123` - `createMissingPermissions()` method

  **Acceptance Criteria**:
  - [ ] Command uses discovered defaults
  - [ ] New `--force` option works
  - [ ] Summary shows discovered vs fallback counts
  - [ ] Command runs without errors

  **QA Scenarios**:
  ```
  Scenario: Sync command runs successfully
    Tool: Bash (docker-compose exec app php artisan permissions:sync --env=testing)
    Steps:
      1. Run sync command
      2. Verify permissions synced
      3. Verify roles have correct permissions
    Expected Result: Command completes successfully
    Evidence: .sisyphus/evidence/task-8-sync-test.txt
  ```

  **Commit**: NO

---

## Final Verification Wave (MANDATORY — after ALL implementation tasks)

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists. For each "Must NOT Have": search codebase for forbidden patterns. Check evidence files exist. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `php artisan test --compact`. Review all changed files for: `as any`/`@ts-ignore`, empty catches, console.log in prod, commented-out code, unused imports. Check AI slop: excessive comments, over-abstraction.
  Output: `Build [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Start from clean state. Execute EVERY QA scenario from EVERY task. Test cross-task integration. Save to `.sisyphus/evidence/final-qa/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff. Verify 1:1 match. Check "Must NOT do" compliance. Flag unaccounted changes.
  Output: `Tasks [N/N compliant] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Success Criteria

### Verification Commands
```bash
php artisan test --compact  # Expected: All tests pass
php artisan permissions:sync --dry-run  # Expected: Shows discovered permissions
php artisan tinker --execute="print_r(app(\App\Services\RolesAndPermissionsService::class)->getDiscoveredDefaultRoleAssignments())"  # Expected: Role-to-permissions mapping
```

### Final Checklist
- [ ] All "Must Have" present
- [ ] All "Must NOT Have" absent
- [ ] All tests pass
- [ ] Backward compatibility maintained
- [ ] Config fallback works
- [ ] Documentation updated
