# Staff Panel — Mirror Admin Resources with Policy-Based Permission Gating

## TL;DR

> **Quick Summary**: Expose every admin resource/cluster/page on the Filament staff panel (`/staff`) so managers and receptionists can use them — but only when the corresponding Spatie permission is granted per-role, per-gym by `ManagerPermissionsPage` / `ReceptionistPermissionsPage`. **The codebase already has `App\Policies\BasePolicy` that does exactly this** — we just need to ensure every resource has a policy, fix the one inconsistent policy, and add admin-only overrides. This is significantly simpler than the original plan.
>
> **Deliverables**:
> - 1 policy refactor (`LeadPolicy` → extends `BasePolicy`)
> - 5-7 new policy classes for resources that lack one (extending `BasePolicy`)
> - 4 admin-only `Resource::canAccess()` overrides (Gym/Manager/Receptionist/BaseEmployee)
> - 3 cluster `canAccess()` overrides (Permissions/Reports/Attendances)
> - ~10 standalone page `canAccess()` overrides
> - `StaffPanelProvider` updated to discover all admin resources/clusters/pages
> - 1 backward-compat data migration granting `getQuickSetupPermissions()` to existing staff users
> - 2 feature test files (12 tests total)
>
> **Estimated Effort**: Small-to-Medium
> **Parallel Execution**: YES — 4 parallel waves
> **Critical Path**: T1 Migration → T2 StaffPanelProvider → T3-T5 Policies → T6-T7 Cluster/Page access → T8-T9 Tests

---

## Context

### Original Request
The `StaffPanelProvider` only registers 6 resources (a subset of admin). The user wants **every** admin resource, cluster, and page available on the staff panel, with each gated by the Spatie permissions that `ManagerPermissionsPage` and `ReceptionistPermissionsPage` already manage. Admin-only items (staff management resources, the Permissions cluster) must remain admin-only regardless of any permission grant.

### Interview Summary
**Key Decisions** (confirmed):
- Navigation groups mirror admin 1:1; each item filtered by `shouldRegisterNavigation()` based on Spatie permission
- `GymResource`, `ManagerResource`, `ReceptionistResource`, `BaseEmployeeResource` → hardcoded `hasAnyRole(['super_admin','admin','gym_owner'])` only (admin-only override)
- `Permissions` cluster (ManagerPermissionsPage, ReceptionistPermissionsPage) → hidden from staff entirely
- **REVISED based on user feedback**: Use existing `App\Policies\*` system (Filament v3 auto-discovers policies). Do NOT create `BaseAdminResource` — the existing `BasePolicy` already implements the Spatie `view_any_{slug}` permission check.

**Research Findings**:
- `BasePermissionsPage` already stores permissions per role per gym and syncs them to users on save
- `TeamsPermission` middleware sets `setPermissionsTeamId($gymId)` + forgets cached permissions
- `App\Policies\BasePolicy` (abstract) implements `viewAny`, `view`, `create`, `update`, `delete`, etc. — all calling `$user->can($action . '_' . $tableName)` which is exactly the Spatie permission check we need
- Filament v3 auto-discovers `App\Policies\{Model}Policy` and calls `viewAny` for list page access; for navigation visibility, calls `Resource::canAccess()` (default true) and then the page-level `viewAny`
- All 6 currently-staff-registered resources already point at `App\Filament\Admin\Resources\*` (shared classes with admin, no duplication)
- `LeadPolicy` is inconsistent — uses `hasAnyRole([MANAGER, GYM_OWNER])` directly instead of Spatie `can()` — should be refactored to extend `BasePolicy`
- Several admin resources have no policy at all (BiometricDevice, Trainer, WhatsApp*, ReportGoal, BaseEmployee, TenantSocialConfig, cluster resources) — need new policy files extending `BasePolicy`

### Risks & Gaps Addressed
1. **Backward compat** (existing staff users may have no Spatie permissions) → **T1 data migration** grants `getQuickSetupPermissions()` defaults
2. **Cache invalidation** (gym switching) → `TeamsPermission` middleware already handles; **T9 verifies**
3. **Cluster sub-pages** (hidden cluster but child pages URL-accessible) → **T6 overrides `canAccess()` on cluster + each child page/resource**
4. **Policy gap** (some resources have no policy) → **T3-T5 create new policies + refactor LeadPolicy**
5. **Admin-only lock** (admin grants permission to staff for an admin-only resource) → **T5 `Resource::canAccess()` override** short-circuits before policy
6. **Discovery cache** → listed as T2 acceptance criteria (run `optimize:clear`)
7. **Widget permissions** (out of scope) → T2 leaves staff widgets commented out

---

## Work Objectives

### Core Objective
Wire the Filament staff panel into the existing Spatie Permission + Policy system so every admin resource/cluster/page appears on the staff panel, gated by the same per-role, per-gym permissions that `BasePermissionsPage` already manages. Use the existing `BasePolicy` rather than creating parallel infrastructure.

### Concrete Deliverables
- 1 new migration: `database/migrations/XXXX_backfill_staff_role_default_permissions.php`
- 1 refactored policy: `app/Policies/LeadPolicy.php` (now extends `BasePolicy`)
- 5-7 new policy files (extending `BasePolicy`):
  - `app/Policies/BiometricDevicePolicy.php`
  - `app/Policies/TrainerPolicy.php`
  - `app/Policies/WhatsAppTemplatePolicy.php`
  - `app/Policies/WhatsAppTransactionPolicy.php`
  - `app/Policies/WhatsAppUsageLogPolicy.php`
  - `app/Policies/ReportGoalPolicy.php`
  - `app/Policies/BaseEmployeePolicy.php`
  - `app/Policies/TenantSocialConfigPolicy.php`
  - `app/Policies/GymMemberAttendancePolicy.php` (cluster resource)
  - `app/Policies/TrainerAttendancePolicy.php` (cluster resource)
  - `app/Policies/ScheduledReportPolicy.php` (cluster resource)
- 4 admin-only `Resource::canAccess()` overrides: GymResource, ManagerResource, ReceptionistResource, BaseEmployeeResource
- 3 cluster `canAccess()` overrides: Permissions, Reports, Attendances
- ~10 standalone page `canAccess()` overrides
- 1 modified provider: `StaffPanelProvider`
- 1 new test base + 2 test files: `StaffPanelTestCase`, `ResourceAccessTest`, `CrossTenantTest`

### Definition of Done
- [ ] `docker-compose exec app composer test -- --filter=StaffPanel` → all tests pass
- [ ] Manager logs into `/staff` → sees only resources they have `view_any_*` permission for
- [ ] Manager visits `/staff/manager` directly → 403 (admin-only resource, even if permission granted)
- [ ] Admin opens Manager Permissions page → toggles a permission → manager sees new item in nav after refresh
- [ ] Manager switches to a different gym → permissions reload (no stale data)
- [ ] `vendor/bin/pint --dirty` → no formatting changes
- [ ] `php artisan test --compact` → no regressions in other tests

### Must Have
- LeadPolicy refactored to extend BasePolicy (consistency)
- Policies for ALL admin resources (extending BasePolicy with correct `$tableName`)
- Admin-only resources override `canAccess()` (role check, not Spatie)
- Permissions cluster hidden from staff
- Reports + Attendances cluster `canAccess()` checks permission
- Backward-compat data migration
- Feature tests for happy + denial + cross-tenant + nav filtering
- `StaffPanelProvider` registers admin resources via discovery

### Must NOT Have (Guardrails)
- **Do NOT create `App\Filament\BaseAdminResource` or any `BaseAdminResource` class** — `BasePolicy` already handles it
- **Do NOT change `extends Resource` on any resource** — Filament auto-uses the policy
- **Do NOT create `App\Filament\Staff\Resources\*` mirrors** — all resources stay in `Admin` namespace
- **Do NOT enable Filament Shield** — would conflict with existing custom permission system
- **Do NOT add widgets to the staff panel** — out of scope
- **Do NOT modify `BasePolicy`** — it's correct as-is
- **Do NOT modify `BasePermissionsPage` or `TeamsPermission` middleware**
- **Do NOT register `Tenancy/*` pages** (RegisterGym, EditGymProfile) on the staff panel
- **Do NOT add new permission names** — only use what `BasePermissionsPage` already grants (`view_any_*`, `view_*`, `create_*`, `edit_*`, `delete_*`, `widget_*`, `access_*`)
- **Do NOT add `before()` hooks to existing policies** — override `Resource::canAccess()` instead for admin-only resources (less invasive)

---

## Verification Strategy (MANDATORY)

> **ZERO HUMAN INTERVENTION** — all verification is agent-executed via PHPUnit and `php artisan route:list`. Test DB is `gym_app_test` per `AGENTS.md` (use `composer test` / `composer test:fresh`).

### Test Decision
- **Infrastructure exists**: YES (PHPUnit v11, `tests/Feature/*` and `tests/Unit/*`)
- **Automated tests**: YES (TDD for new policies, tests-after for `canAccess()` overrides)
- **Framework**: PHPUnit v11
- **For each new policy**: One feature test asserting `viewAny` returns false without permission, true with permission
- **For each admin-only override**: One feature test asserting manager with permission STILL gets denied
- **For StaffPanelProvider changes**: One smoke test via `php artisan route:list --path=staff`

### QA Policy
Every task with logic includes at least one PHPUnit feature test. Policy additions and `canAccess()` overrides are verified by the umbrella test in T8. No human QA — all QA scenarios run via `php artisan test`.

### Test-DB Safety
- All test commands use `docker-compose exec app composer test` (uses `gym_app_test` via `phpunit.xml` `force="true"` `DB_DATABASE` override)
- **NEVER** run `migrate:fresh` without `--env=testing` (per `AGENTS.md`)

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation, 3 parallel tasks):
├── T1: Backward-compat data migration
├── T2: StaffPanelProvider → discover all admin resources/clusters/pages
└── T3: Test fixtures + base test case (StaffPanelTestCase)

Wave 2 (Policies + admin-only overrides, 3 parallel tasks):
├── T4: Refactor LeadPolicy → extend BasePolicy
├── T5: Create new policies for resources that lack one (10+ files batched)
└── T6: Admin-only Resource::canAccess() overrides (4 files)

Wave 3 (Cluster + page access overrides, 2 parallel tasks):
├── T7: Cluster-level canAccess (Permissions → admin only; Reports/Attendances → permission gated)
└── T8: Standalone admin pages canAccess overrides (~10 files)

Wave 4 (Tests, 2 parallel tasks):
├── T9: ResourceAccessTest — happy path + denial + nav filtering
└── T10: CrossTenantTest — gym switching + cache invalidation

Final Verification (4 parallel review agents):
├── F1: Plan compliance audit (oracle)
├── F2: Code quality review (unspecified-high)
├── F3: Real automated test run (deep)
└── F4: Scope fidelity check (deep)
```

### Dependency Matrix

- **T1**: — — T9
- **T2**: — — T9
- **T3**: — — T9, T10
- **T4**: — — T9
- **T5**: — — T9
- **T6**: T5 — T9
- **T7**: T5 — T9
- **T8**: T5 — T9
- **T9**: T1, T2, T3, T4, T5, T6, T7, T8 — F1-F4
- **T10**: T3 — F1-F4

### Agent Dispatch Summary

- **Wave 1**: T1 → `unspecified-high`, T2 → `quick`, T3 → `unspecified-high`
- **Wave 2**: T4 → `quick`, T5 → `unspecified-high` (largest task), T6 → `quick`
- **Wave 3**: T7 → `quick`, T8 → `quick`
- **Wave 4**: T9 → `unspecified-high`, T10 → `unspecified-high`
- **Final**: F1 → `oracle`, F2 → `unspecified-high`, F3 → `deep`, F4 → `deep`

---

## TODOs

> **Implementation + Test = ONE Task. Never separate.**
> **EVERY task MUST have Agent-Executed QA Scenarios.**

- [ ] 1. Create backward-compat data migration for existing staff users

  **What to do**:
  - Generate migration: `docker-compose exec app php artisan make:migration backfill_staff_role_default_permissions --no-interaction`
  - Rename timestamp prefix to put it after the latest existing migration
  - In `up()`:
    - For each `Gym::all()`, find or create `Role` with `name = 'manager'` and `gym_id = $gym->id` (same for `receptionist`)
    - Look up the role's `PermissionSetting` (via `permission_settings` table, `role` column)
    - If `PermissionSetting` exists, use its `default_permissions` array
    - Otherwise, fall back to the same list as `ManagerPermissionsPage::getQuickSetupPermissions()` (defined inline in the migration — copy the array verbatim, do NOT import the class since it's a Filament Livewire component)
    - For each permission name: `Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])`
    - `setPermissionsTeamId($gym->id)` before sync (so role permission context is correct)
    - `Role::find($roleId)->syncPermissions($permissions)` (in the current team context)
    - Then call `app(RolesAndPermissionsService::class)->syncRolePermissionsToUsers($role, $gym)` to update existing users
  - In `down()`: do NOT delete the permissions (they may have been edited by admins); log a warning
  - Wrap each gym in a `DB::transaction()` so a failure on one gym doesn't corrupt others
  - Add feature test at `tests/Feature/Permissions/BackfillStaffRolePermissionsTest.php`

  **Must NOT do**:
  - Do NOT drop or alter existing `permissions` table
  - Do NOT remove custom permissions an admin has already granted
  - Do NOT touch `super_admin` / `admin` / `gym_owner` roles
  - Do NOT delete or modify `PermissionSetting` rows
  - Do NOT import `ManagerPermissionsPage` or `BasePermissionsPage` in the migration

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: `[]`
  - **Reason**: Data migration touching existing user data; must be idempotent and safe to run on production.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T9
  - **Blocked By**: None

  **References**:
  - `app/Filament/BasePages/BasePermissionsPage.php:164-217` — `save()` is the gold-standard permission sync flow
  - `app/Filament/BasePages/BasePermissionsPage.php:219-233` — `updateRoleUsers()` syncs per-user
  - `app/Enums/DefaultRole.php:12-13` — `MANAGER` and `RECEPTIONIST` values
  - `app/Filament/Admin/Clusters/Permissions/Pages/ManagerPermissionsPage.php:31-59` — exact `getQuickSetupPermissions()` array (copy into migration)
  - `app/Filament/Admin/Clusters/Permissions/Pages/ReceptionistPermissionsPage.php:31-59` — same as Manager (use same array)

  **Acceptance Criteria**:
  - [ ] Migration file exists in `database/migrations/` with timestamp after latest existing
  - [ ] `up()` iterates all gyms, sets team context, syncs permissions for `manager` + `receptionist` roles
  - [ ] `down()` is a safe no-op (log only, no destructive action)
  - [ ] Migration is idempotent (running twice produces same final state)
  - [ ] `docker-compose exec app php artisan migrate --env=testing --pretend` shows correct SQL
  - [ ] One feature test: `BackfillStaffRolePermissionsTest::test_grants_quick_setup_permissions_to_existing_manager`
  - [ ] `vendor/bin/pint --dirty` clean

  **QA Scenarios**:
  ```
  Scenario: Migration grants quick-setup permissions to existing manager
    Tool: Bash (phpunit)
    Preconditions: test DB has 1 gym, 1 manager user with no permissions
    Steps:
      1. Run: docker-compose exec app php artisan test --compact --filter='BackfillStaffRolePermissionsTest::test_grants_quick_setup_to_existing_manager'
    Expected Result: PASS — manager role has all permissions from getQuickSetupPermissions()
    Evidence: .sisyphus/evidence/t1-backfill-manager.txt

  Scenario: Migration is idempotent
    Tool: Bash (phpunit)
    Preconditions: migration already ran
    Steps:
      1. Run: docker-compose exec app php artisan test --compact --filter='BackfillStaffRolePermissionsTest::test_idempotent'
    Expected Result: PASS — running twice doesn't duplicate or remove permissions
    Evidence: .sisyphus/evidence/t1-idempotent.txt

  Scenario: Migration uses PermissionSetting override when present
    Tool: Bash (phpunit)
    Preconditions: PermissionSetting row exists for 'manager' with custom default_permissions
    Steps:
      1. Run: docker-compose exec app php artisan test --compact --filter='BackfillStaffRolePermissionsTest::test_uses_permission_setting_override'
    Expected Result: PASS — manager role has the custom override, not the hardcoded fallback
    Evidence: .sisyphus/evidence/t1-setting-override.txt
  ```

  **Commit**: NO

- [ ] 2. Update `StaffPanelProvider` to discover all admin resources/clusters/pages

  **What to do**:
  - In `app/Providers/Filament/StaffPanelProvider.php`:
    - Keep the existing `->navigationGroups([...])` (mirrors admin 1:1 per user decision)
    - **Remove** the explicit `->resources([...])` array (only 6; we want full discovery)
    - **Add** `->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')` — same pattern as `AdminPanelProvider` line 109
    - **Add** `->discoverClusters(in: app_path('Filament/Admin/Clusters'), for: 'App\\Filament\\Admin\\Clusters')`
    - **Add** `->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')`
    - Keep the existing `->discoverResources/Widgets/Pages` for `Filament/Staff/*` (staff-only additions like `Dashboard`)
    - Keep `->pages([\App\Filament\Staff\Pages\Dashboard::class])`
    - Keep `->discoverWidgets(in: app_path('Filament/Staff/Widgets'), for: 'App\\Filament\\Staff\\Widgets')`
    - Keep `->widgets([Widgets\AccountWidget::class, Widgets\FilamentInfoWidget::class])`
    - **Do NOT** add any widgets to admin
  - After file change: run `docker-compose exec app php artisan optimize:clear` and `docker-compose exec app php artisan view:clear` to flush cached discovery

  **Must NOT do**:
  - Do NOT register `Filament/Tenant/Pages/*` (Tenancy pages are admin-only)
  - Do NOT change colors, brand, or middleware
  - Do NOT change the path (`/staff`), id (`staff`), or tenant config
  - Do NOT add `FilamentShieldPlugin` to plugins

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: `[]`
  - **Reason**: Mechanical panel config change; one file.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T9
  - **Blocked By**: None (can run before T1 since it's a config-only change)

  **References**:
  - `app/Providers/Filament/AdminPanelProvider.php:109-112` — exact discovery pattern to copy
  - `app/Providers/Filament/StaffPanelProvider.php:81-94` — current registration to replace

  **Acceptance Criteria**:
  - [ ] `StaffPanelProvider::panel()` calls `discoverResources`, `discoverClusters`, `discoverPages` for `App\Filament\Admin\*`
  - [ ] Explicit `->resources([...])` array removed (or kept as a comment)
  - [ ] Existing `Filament/Staff/*` discoveries still present
  - [ ] Tenancy pages NOT discovered
  - [ ] `docker-compose exec app php artisan optimize:clear` runs without error
  - [ ] One smoke test: `php artisan route:list --path=staff` shows admin resource routes under `/staff/{tenant}/*`
  - [ ] `vendor/bin/pint --dirty` clean

  **QA Scenarios**:
  ```
  Scenario: Staff panel route list shows admin resources
    Tool: Bash (artisan route:list)
    Preconditions: StaffPanelProvider updated, cache cleared
    Steps:
      1. docker-compose exec app php artisan route:list --path=staff 2>&1 | grep -E '(gym-member|expense|whatsapp|lead|trainer)' | head -20
    Expected Result: 5+ admin resource routes appear under /staff/{tenant}/*
    Evidence: .sisyphus/evidence/t2-route-list.txt

  Scenario: Staff panel does NOT register Tenancy pages
    Tool: Bash (artisan route:list)
    Preconditions: StaffPanelProvider updated
    Steps:
      1. docker-compose exec app php artisan route:list --path=staff 2>&1 | grep -E '(register-gym|edit-gym-profile)' || echo "EMPTY: not registered"
    Expected Result: EMPTY (Tenancy pages not on staff panel)
    Evidence: .sisyphus/evidence/t2-no-tenancy.txt
  ```

  **Commit**: NO

- [ ] 3. Create test fixtures and base test case for staff panel tests

  **What to do**:
  - Create `tests/Feature/Filament/StaffPanel/StaffPanelTestCase.php`
  - Abstract class extending `Tests\TestCase` (the existing one from the project)
  - `setUp()`:
    - Create a test gym: `Gym::factory()->create()`
    - Create 3 test users:
      - `$this->adminUser` = `User::factory()->create()` + assign `gym_owner` role for test gym
      - `$this->managerUser` = `User::factory()->create()` + assign `manager` role for test gym
      - `$this->receptionistUser` = `User::factory()->create()` + assign `receptionist` role for test gym
    - Set `setPermissionsTeamId($gym->id)`
    - `Filament::setCurrentPanel('staff')`
  - Helper methods:
    - `grantPermission(User $user, string $name): void` — `firstOrCreate` permission, then `$user->givePermissionTo($permission)` scoped to team
    - `actAsManager(): void`, `actAsReceptionist(): void`, `actAsAdmin(): void`
    - `getStaffUrl(string $resource): string` — returns `/{tenant->slug}/staff/{resource}`
    - `assertStaffCanAccess(string $resource, User $user): void` — assert 200
    - `assertStaffCannotAccess(string $resource, User $user): void` — assert 403/redirect

  **Must NOT do**:
  - Do NOT use `RefreshDatabase` directly — use the test DB setup already provided by `tests/TestCase.php`
  - Do NOT create real `Gym`, `User` records without factories
  - Do NOT touch `gym_app` (main) DB — use `gym_app_test` only

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: `[]`
  - **Reason**: Test infrastructure that all subsequent tests depend on; must use existing project test conventions.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T9, T10
  - **Blocked By**: None

  **References**:
  - `tests/TestCase.php` — project's base test case (uses `gym_app_test`)
  - `database/factories/GymFactory.php` — Gym factory pattern
  - `database/factories/UserFactory.php` — User factory pattern
  - `app/Models/Gym.php` — Gym model
  - `app/Models/User.php` — User model with `HasRoles` trait

  **Acceptance Criteria**:
  - [ ] File exists at `tests/Feature/Filament/StaffPanel/StaffPanelTestCase.php`
  - [ ] Extends project's `Tests\TestCase`
  - [ ] `setUp()` creates 3 users + 1 gym
  - [ ] Helpers `grantPermission`, `actAs*`, `getStaffUrl`, `assertStaff*` exist
  - [ ] `vendor/bin/pint --dirty` clean
  - [ ] One trivial test extending this class passes: `dummy test that just calls setUp()`

  **QA Scenarios**:
  ```
  Scenario: Test case setUp creates 3 users + 1 gym
    Tool: Bash (phpunit)
    Preconditions: test DB clean
    Steps:
      1. Run: docker-compose exec app php artisan test --compact --filter='StaffPanelTestCaseSmokeTest::test_setUp_creates_three_users'
    Expected Result: PASS — 3 users + 1 gym in DB
    Evidence: .sisyphus/evidence/t3-setUp.txt

  Scenario: grantPermission helper assigns permission to user
    Tool: Bash (phpunit)
    Preconditions: StaffPanelTestCase::setUp
    Steps:
      1. $this->grantPermission($this->managerUser, 'view_any_gym_member')
      2. Assert: $this->managerUser->fresh()->can('view_any_gym_member') === true
    Expected Result: PASS
    Evidence: .sisyphus/evidence/t3-grant-permission.txt
  ```

  **Commit**: NO

- [ ] 4. Refactor `LeadPolicy` to extend `BasePolicy` for consistency

  **What to do**:
  - Rewrite `app/Policies/LeadPolicy.php`:
    - Remove all the explicit `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete` methods
    - Replace with: `class LeadPolicy extends BasePolicy { protected static string $tableName = 'lead'; }`
  - This makes `LeadPolicy` consistent with `GymMemberPolicy`, `GymPolicy`, `MembershipPlanPolicy`, etc.
  - **Important**: Verify the Spatie permission names exist for `lead` table:
    - `view_any_lead`, `view_lead`, `create_lead`, `update_lead`, `delete_lead`, `delete_any_lead`, `force_delete_lead`, `force_delete_any_lead`, `restore_lead`, `restore_any_lead`, `replicate_lead`, `reorder_lead`
  - If any are missing, the admin's permission page won't show them as toggleable. Check `SELECT name FROM permissions WHERE name LIKE '%lead%';` and add missing entries via a separate small migration or via `BasePermissionsPage::save()` if a `PermissionSetting` is created.
  - The old logic (`hasAnyRole([MANAGER, GYM_OWNER])`) is now superseded — staff managers get `view_any_lead` from the default permission set, and receptionists would need it explicitly granted (or use the shared default).
  - **Test**: LeadPolicyTest::test_viewAny_returns_permission_check (no manager role check)

  **Must NOT do**:
  - Do NOT change `LeadResource` itself
  - Do NOT change other policies
  - Do NOT remove custom logic if the current `LeadPolicy` has special business rules not captured by `view_any_lead` (review the file carefully before rewriting — current file shows it only checks role, so safe to refactor)
  - Do NOT add new permission names

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: `[]`
  - **Reason**: Mechanical refactor of 1 policy file.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T9
  - **Blocked By**: None

  **References**:
  - `app/Policies/LeadPolicy.php` — current (inconsistent — uses `hasAnyRole`)
  - `app/Policies/BasePolicy.php` — target pattern
  - `app/Policies/GymMemberPolicy.php` — example of correct usage
  - `app/Policies/GymPolicy.php` — another example

  **Acceptance Criteria**:
  - [ ] `LeadPolicy` extends `BasePolicy` and sets `$tableName = 'lead'`
  - [ ] All explicit method bodies removed
  - [ ] `vendor/bin/pint --dirty` clean
  - [ ] One test: `LeadPolicyTest::test_viewAny_checks_spatie_permission_not_role`
  - [ ] Existing LeadResource tests still pass

  **QA Scenarios**:
  ```
  Scenario: LeadPolicy::viewAny uses Spatie permission
    Tool: Bash (phpunit)
    Preconditions: manager user with no permissions
    Steps:
      1. $manager = $this->managerUser
      2. $policy = new LeadPolicy()
      3. Assert: $policy->viewAny($manager) === false (no permission)
      4. $this->grantPermission($manager, 'view_any_lead')
      5. Assert: $policy->viewAny($manager) === true
    Expected Result: PASS — viewAny delegates to Spatie permission, not role
    Evidence: .sisyphus/evidence/t4-lead-policy.txt
  ```

  **Commit**: NO

- [ ] 5. Create new policy classes for resources that lack one

  **What to do**:
  - Verify each new policy's `$tableName` matches the actual Spatie permission prefix in the `permissions` table
  - Create these new policy files (each is 1-line, extending `BasePolicy`):
    - `app/Policies/BiometricDevicePolicy.php` → `protected static string $tableName = 'biometric_device';`
    - `app/Policies/TrainerPolicy.php` → `protected static string $tableName = 'trainer';`
    - `app/Policies/WhatsAppTemplatePolicy.php` → `protected static string $tableName = 'whatsapp_template';`
    - `app/Policies/WhatsAppTransactionPolicy.php` → `protected static string $tableName = 'whatsapp_transaction';`
    - `app/Policies/WhatsAppUsageLogPolicy.php` → `protected static string $tableName = 'whatsapp_usage_log';`
    - `app/Policies/ReportGoalPolicy.php` → `protected static string $tableName = 'report_goal';`
    - `app/Policies/BaseEmployeePolicy.php` → `protected static string $tableName = 'base_employee';` (or whatever the model is — check the resource)
    - `app/Policies/TenantSocialConfigPolicy.php` → `protected static string $tableName = 'tenant_social_config';`
    - `app/Policies/GymMemberAttendancePolicy.php` → `protected static string $tableName = 'gym_member_attendance';` (cluster resource, in `app/Policies/` not in `Clusters/`)
    - `app/Policies/TrainerAttendancePolicy.php` → `protected static string $tableName = 'trainer_attendance';`
    - `app/Policies/ScheduledReportPolicy.php` → `protected static string $tableName = 'scheduled_report';`
  - For each, run `grep 'protected static ?string $model' app/Filament/Admin/Resources/{ResourceName}.php` to confirm the actual Eloquent model class, then `head -1 app/Models/{ModelName}.php` to confirm the model's table name
  - Verify the corresponding Spatie permissions exist:
    ```bash
    docker-compose exec app php artisan tinker --execute="echo \Spatie\Permission\Models\Permission::where('name', 'LIKE', '%{table_name}%')->pluck('name')->implode(\"\\n\");"
    ```
  - If any permissions are missing, document them — they'll be added by the `ManagerPermissionsPage` once a `PermissionSetting` is configured for the role, OR via a seeder
  - One feature test: `tests/Feature/Policies/BasePolicyIntegrationTest.php` that asserts for each new policy: `viewAny` returns false without permission, true with permission

  **Must NOT do**:
  - Do NOT add explicit methods to the new policies — let `BasePolicy` handle everything
  - Do NOT add `before()` hooks
  - Do NOT add business logic specific to any model
  - Do NOT modify `BasePolicy` itself
  - Do NOT modify the resource files

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: `[]`
  - **Reason**: 11 new files (mostly 1-line) but requires verification of model table names and permission name existence.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T6, T7, T8, T9
  - **Blocked By**: None

  **References**:
  - `app/Policies/BasePolicy.php` — base class
  - `app/Policies/GymMemberPolicy.php` — pattern to follow
  - `app/Policies/GymPolicy.php` — another example
  - `app/Filament/Admin/Resources/BiometricDeviceResource.php` — check `$model`
  - `app/Filament/Admin/Resources/TrainerResource.php` — check `$model`
  - `app/Filament/Admin/Clusters/Attendances/Resources/GymMemberAttendanceResource.php` — check `$model`
  - `app/Filament/Admin/Clusters/Attendances/Resources/TrainerAttendanceResource.php` — check `$model`
  - `app/Filament/Admin/Clusters/Reports/Resources/ScheduledReportResource.php` — check `$model`
  - `app/Models/` — confirm each model's `$table` if non-standard

  **Acceptance Criteria**:
  - [ ] All 11 new policy files exist
  - [ ] Each extends `BasePolicy` and sets `$tableName` to the correct slug
  - [ ] Each file passes `php -l` (no syntax errors)
  - [ ] `vendor/bin/pint --dirty` clean
  - [ ] One feature test: `BasePolicyIntegrationTest` with 11 assertions (one per new policy)
  - [ ] No existing policy file modified

  **QA Scenarios**:
  ```
  Scenario: All 11 new policies route Spatie permissions correctly
    Tool: Bash (phpunit)
    Preconditions: test DB with all 11 permissions pre-seeded
    Steps:
      1. Run: docker-compose exec app php artisan test --compact --filter='BasePolicyIntegrationTest'
    Expected Result: PASS — 11 policies all delegate to Spatie permissions correctly
    Evidence: .sisyphus/evidence/t5-new-policies.txt

  Scenario: No BasePolicy modifications
    Tool: Bash (diff)
    Preconditions: T5 complete
    Steps:
      1. git diff app/Policies/BasePolicy.php
    Expected Result: empty (BasePolicy untouched)
    Evidence: .sisyphus/evidence/t5-basepolicy-untouched.txt
  ```

  **Commit**: NO

- [ ] 6. Add `canAccess()` overrides to admin-only resources

  **What to do**:
  - For these 4 resource files, add `public static function canAccess(): bool` override (do NOT change `extends Resource`):
    - `app/Filament/Admin/Resources/GymResource.php`
    - `app/Filament/Admin/Resources/ManagerResource.php`
    - `app/Filament/Admin/Resources/ReceptionistResource.php`
    - `app/Filament/Admin/Resources/BaseEmployeeResource.php`
  - Each gets this exact override:
    ```php
    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user?->hasAnyRole(['super_admin', 'admin', 'gym_owner']) ?? false;
    }
    ```
  - This short-circuits BEFORE Filament consults the policy. So even if admin grants `view_any_manager` to a manager, the resource is still hidden from staff
  - Place the method just below `protected static ?string $model` declaration for consistency with the codebase
  - Add `use Illuminate\Support\Facades\Auth;` if not present (probably already there from other code)

  **Must NOT do**:
  - Do NOT change `extends Resource` — leave it alone
  - Do NOT change the model binding, navigation label, or any other resource configuration
  - Do NOT add `shouldRegisterNavigation()` (default behavior of returning `canAccess()` result is what we want)
  - Do NOT modify the policies (`GymPolicy` is fine, others probably use `UserPolicy`)
  - Do NOT check Spatie permission in this override — that's the point, we deny regardless of grant

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: `[]`
  - **Reason**: 4 mechanical method additions, same pattern.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T9
  - **Blocked By**: T5 (so the policy exists for the policy-check path that follows the canAccess override)

  **References**:
  - `app/Filament/Admin/Resources/GymResource.php` — current
  - `app/Filament/Admin/Resources/ManagerResource.php` — current (uses User model)
  - `app/Filament/Admin/Resources/ReceptionistResource.php` — current (uses User model)
  - `app/Filament/Admin/Resources/BaseEmployeeResource.php` — current
  - `app/Filament/Admin/Clusters/Permissions/Pages/ManagerPermissionsPage.php:235-239` — `BasePermissionsPage::canAccess()` uses `hasRole('gym_owner')` — similar pattern

  **Acceptance Criteria**:
  - [ ] All 4 files: `public static function canAccess(): bool` method added
  - [ ] All 4 files: `canAccess()` returns `$user?->hasAnyRole(['super_admin','admin','gym_owner']) ?? false`
  - [ ] `php -l` clean
  - [ ] `vendor/bin/pint --dirty` clean
  - [ ] One test (deferred to T9) confirms manager cannot access these even when permission is granted

  **QA Scenarios**:
  ```
  Scenario: All 4 resources have canAccess override
    Tool: Bash (grep)
    Preconditions: T5 complete
    Steps:
      1. for f in GymResource ManagerResource ReceptionistResource BaseEmployeeResource; do
           echo "=== $f ==="
           grep -A 3 'public static function canAccess' app/Filament/Admin/Resources/$f.php
         done
    Expected Result: All 4 files show hasAnyRole(['super_admin','admin','gym_owner']) check
    Evidence: .sisyphus/evidence/t6-canaccess.txt
  ```

  **Commit**: NO

- [ ] 7. Add cluster-level `canAccess()` overrides (Permissions, Reports, Attendances)

  **What to do**:
  - In `app/Filament/Admin/Clusters/Permissions.php`:
    - Add `public static function canAccess(): bool` returning `$user?->hasRole('gym_owner') ?? false`
    - This hides the entire cluster from `/staff` sidebar (ManagerPermissionsPage and ReceptionistPermissionsPage won't appear)
    - Note: `ManagerPermissionsPage` and `ReceptionistPermissionsPage` already have `canAccess()` hardcoded to `hasRole('gym_owner')` (in `BasePermissionsPage`) — they won't be accessible even via direct URL
  - In `app/Filament/Admin/Clusters/Reports.php`:
    - Add `public static function canAccess(): bool`:
      ```php
      $user = auth()->user();
      if (! $user) return false;
      if ($user->hasAnyRole(['super_admin','admin','gym_owner'])) return true;
      return $user->can('view_any_report') || $user->can('view_reports') || $user->can('view_any_scheduled_report');
      ```
    - Verify the permission names exist: `SELECT DISTINCT name FROM permissions WHERE name LIKE '%report%' OR name LIKE '%scheduled%';`
  - In `app/Filament/Admin/Clusters/Attendances.php`:
    - Add `public static function canAccess(): bool` checking `view_any_attendance` or `view_attendance` permission (verify with `SELECT DISTINCT name FROM permissions WHERE name LIKE '%attendance%';`)
  - Verify each cluster class extends `Filament\Clusters\Cluster`

  **Must NOT do**:
  - Do NOT remove the cluster's child pages/resources (they remain registered; just hidden from sidebar)
  - Do NOT forget that cluster-level `canAccess()` only hides the cluster's nav — child resources (T5 policies) handle their own access
  - Do NOT add widget-level checks here

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: `[]`
  - **Reason**: 3 small file edits with role/permission checks.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3
  - **Blocks**: T9
  - **Blocked By**: T5 (so child resource policies exist)

  **References**:
  - `app/Filament/Admin/Clusters/Permissions.php` — Permissions cluster class
  - `app/Filament/Admin/Clusters/Reports.php` — Reports cluster class
  - `app/Filament/Admin/Clusters/Attendances.php` — Attendances cluster class
  - `app/Filament/Admin/Clusters/Permissions/Pages/ManagerPermissionsPage.php:235-239` — pattern: `canAccess()` checks `hasRole('gym_owner')`

  **Acceptance Criteria**:
  - [ ] All 3 cluster files: `public static function canAccess(): bool` method added
  - [ ] Permissions cluster: only `hasRole('gym_owner')` (or admin roles) returns true
  - [ ] Reports cluster: admin bypass + at least one `view_*report*` permission
  - [ ] Attendances cluster: admin bypass + at least one `view_*attendance*` permission
  - [ ] `php -l` clean
  - [ ] `vendor/bin/pint --dirty` clean

  **QA Scenarios**:
  ```
  Scenario: All 3 cluster files have canAccess override
    Tool: Bash (grep)
    Preconditions: T5 complete
    Steps:
      1. for f in Permissions Reports Attendances; do
           echo "=== $f ==="
           grep -A 5 'public static function canAccess' app/Filament/Admin/Clusters/$f.php
         done
    Expected Result: 3 files, each with canAccess override
    Evidence: .sisyphus/evidence/t7-cluster-canaccess.txt
  ```

  **Commit**: NO

- [ ] 8. Add `canAccess()` overrides to standalone admin pages

  **What to do**:
  - Add `public static function canAccess(): bool` to these Filament `Page` classes in `app/Filament/Admin/Pages/`:
    - `Dashboard.php` → `return auth()->user()?->hasAnyRole(['super_admin','admin','gym_owner','manager','receptionist']) ?? false;` (allow all staff)
    - `LeadKanbanBoard.php` → check `view_any_lead` permission (admin bypass)
    - `QRCodeManager.php` → admin only (no specific permission in DB)
    - `WhatsAppSetup.php` → check `access_whatsapp_setup` or `widget_*` permission (admin bypass); default admin only if none exist
    - `GymSettings.php` → admin/owner only (gym-level config)
    - `ExpenseReports.php` → admin only (no specific permission currently)
    - `BiometricDevice/EnrollMember.php` → check `view_any_gym_member` + `manage_biometric` (admin bypass)
    - `WhatsApp/Broadcast.php` → check `view_any_whatsapp_template` or `send_broadcast` (admin bypass)
    - `WhatsApp/ManualWabaConnection.php` → admin only
    - `WhatsApp/PurchaseCredits.php` → admin only
    - `WhatsApp/WhatsappDashboard.php` → admin only (or check `view_whatsapp_dashboard` if it exists)
  - For each, verify the permission name actually exists in your `permissions` table (or document that it doesn't, and use admin-only)
  - Use this pattern:
    ```php
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        if ($user->hasAnyRole(['super_admin','admin','gym_owner'])) return true;
        return $user->can('view_any_lead');  // example
    }
    ```

  **Must NOT do**:
  - Do NOT skip the admin/owner bypass
  - Do NOT invent new permission names — verify each via `SELECT name FROM permissions WHERE name LIKE '%{slug}%';` first
  - Do NOT touch `Tenancy/EditGymProfile.php` or `Tenancy/RegisterGym.php`

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: `[]`
  - **Reason**: ~10 file edits, all the same pattern.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3
  - **Blocks**: T9
  - **Blocked By**: T5 (for permission-name verification)

  **References**:
  - `app/Filament/Admin/Pages/Dashboard.php` — current page
  - `app/Filament/Admin/Pages/LeadKanbanBoard.php` — current
  - `app/Filament/Admin/Pages/QRCodeManager.php` — current
  - `app/Filament/Admin/Pages/WhatsAppSetup.php` — current
  - `app/Filament/Admin/Pages/GymSettings.php` — current
  - `app/Filament/Admin/Pages/ExpenseReports.php` — current
  - `app/Filament/Admin/Pages/BiometricDevice/EnrollMember.php` — current
  - `app/Filament/Admin/Pages/WhatsApp/Broadcast.php` — current
  - `app/Filament/Admin/Pages/WhatsApp/ManualWabaConnection.php` — current
  - `app/Filament/Admin/Pages/WhatsApp/PurchaseCredits.php` — current
  - `app/Filament/Admin/Pages/WhatsApp/WhatsappDashboard.php` — current
  - `app/Filament/Admin/Clusters/Permissions/Pages/ManagerPermissionsPage.php:235-239` — pattern for role check

  **Acceptance Criteria**:
  - [ ] All ~10 page files: `canAccess()` method added
  - [ ] Admin/owner bypass in each
  - [ ] Permission name verified to exist (or default to admin-only if not)
  - [ ] `php -l` clean
  - [ ] `vendor/bin/pint --dirty` clean
  - [ ] `Tenancy/*` pages NOT modified (admin-only by registration)

  **QA Scenarios**:
  ```
  Scenario: All admin pages have canAccess override
    Tool: Bash (grep)
    Preconditions: T5 complete
    Steps:
      1. for f in $(find app/Filament/Admin/Pages -name '*.php' -not -path '*Tenancy*'); do
           grep -L 'public static function canAccess' "$f" | head -1
         done | head -20
    Expected Result: 0 lines (every page has canAccess)
    Evidence: .sisyphus/evidence/t8-pages-canaccess.txt
  ```

  **Commit**: NO

- [ ] 9. Add `ResourceAccessTest` — happy path + denial + nav filtering

  **What to do**:
  - Create `tests/Feature/Filament/StaffPanel/ResourceAccessTest.php`
  - Extends `StaffPanelTestCase` (T3)
  - Test methods:
    1. `test_manager_with_view_any_gym_member_can_access_staff_gym_members_index`
       - Grant `view_any_gym_member` to manager
       - GET `/staff/{tenant}/gym-members` → assert 200
    2. `test_manager_without_view_any_gym_member_cannot_access_staff_gym_members`
       - No permission granted
       - GET `/staff/{tenant}/gym-members` → assert 403 or redirect
    3. `test_manager_cannot_access_admin_only_resource_even_with_permission`
       - Grant `view_any_manager` to manager (yes, manager is the resource)
       - GET `/staff/{tenant}/managers` → assert 403 (canAccess override short-circuits)
    4. `test_staff_panel_does_not_show_permissions_cluster_in_nav`
       - Render the staff panel sidebar for manager
       - Assert no link with text "Manager Permissions" or "Receptionist Permissions"
    5. `test_staff_panel_hides_resources_without_permission`
       - Manager has only `view_any_gym_member`
       - Render sidebar → assert "Gym Members" present, "Leads" absent (no `view_any_lead` granted), "Trainers" absent
    6. `test_admin_owner_sees_all_resources_in_nav`
       - `adminUser` (gym_owner) → render sidebar → assert Gym Members, Leads, Trainers, etc. all present
    7. `test_receptionist_with_view_any_payment_can_access_payments`
       - Similar to test 1 but for receptionist
    8. `test_new_policy_resources_use_basepolicy`
       - For each of the 11 new policies from T5: assert `viewAny` returns false without permission, true with permission
  - For each test: capture the rendered output and assert specific text/element presence via `Livewire::test()` assertions
  - Use `Filament::setCurrentPanel('staff')` at start of each test

  **Must NOT do**:
  - Do NOT use `actingAs()` patterns that bypass tenant scoping
  - Do NOT mock the Spatie `can()` method — test the real permission check
  - Do NOT skip `setPermissionsTeamId()`

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: `['phpunit-best-practices']`
  - **Reason**: Multi-tenant test setup with Filament + Spatie Permissions.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 4
  - **Blocks**: F1-F4
  - **Blocked By**: T1, T2, T3, T4, T5, T6, T7, T8

  **References**:
  - `tests/Feature/Filament/StaffPanel/StaffPanelTestCase.php` (T3) — base class
  - `app/Policies/BasePolicy.php` — policy structure being tested
  - `app/Http/Middleware/TeamsPermission.php` — must run before permission check
  - PHPUnit Filament patterns: search for `livewire(` and `assertCanSeeTableRecords` in existing tests

  **Acceptance Criteria**:
  - [ ] File `tests/Feature/Filament/StaffPanel/ResourceAccessTest.php` exists
  - [ ] 8 test methods, all pass
  - [ ] `docker-compose exec app composer test -- --filter=ResourceAccessTest` → all green
  - [ ] `vendor/bin/pint --dirty` clean
  - [ ] All assertions use specific selectors/text, not vague checks

  **QA Scenarios**:
  ```
  Scenario: All ResourceAccessTest tests pass
    Tool: Bash (phpunit)
    Preconditions: T1-T8 complete
    Steps:
      1. docker-compose exec app composer test -- --filter=ResourceAccessTest 2>&1
    Expected Result: 8 tests, all green
    Evidence: .sisyphus/evidence/t9-resource-access.txt
  ```

  **Commit**: NO

- [ ] 10. Add `CrossTenantTest` — gym switching + cache invalidation

  **What to do**:
  - Create `tests/Feature/Filament/StaffPanel/CrossTenantTest.php`
  - Extends `StaffPanelTestCase` (T3)
  - Test methods:
    1. `test_manager_in_gym_a_with_permission_sees_resource`
       - Set up: gym A, manager with `view_any_gym_member` for gym A
       - GET `/staff/{gym-a-slug}/gym-members` → 200
    2. `test_same_manager_in_gym_b_without_permission_is_denied`
       - Set up: gym B (different gym), same manager user, NO permission for gym B
       - GET `/staff/{gym-b-slug}/gym-members` → 403 or redirect
    3. `test_switching_gym_id_reloads_permissions`
       - Manager has permission in gym A only
       - Request gym A → 200
       - Request gym B → 403
       - Request gym A → 200 (cache should be cleared and re-loaded)
    4. `test_permission_cache_is_cleared_on_tenant_change`
       - Verify `User::forgetCachedPermissions()` is called when tenant changes
       - OR: assert the cache key changes between gyms
    5. `test_role_check_uses_correct_team_context`
       - Manager has `manager` role for gym A but not gym B
       - GET `/staff/{gym-a-slug}/dashboard` → 200
       - GET `/staff/{gym-b-slug}/dashboard` → 403 (no role in gym B)
  - For each test: use `Session::put('permission_team_id', $gymId)` and `setPermissionsTeamId($gymId)` to simulate the middleware

  **Must NOT do**:
  - Do NOT use `Cache::flush()` to "fix" cache issues — test the real cache invalidation
  - Do NOT bypass the `TeamsPermission` middleware in tests

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: `['phpunit-best-practices']`
  - **Reason**: Multi-tenant cache + permission flow testing — trickiest part of the system.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 4
  - **Blocks**: F1-F4
  - **Blocked By**: T3

  **References**:
  - `app/Http/Middleware/TeamsPermission.php` — the cache invalidation logic
  - `app/Filament/BasePages/BasePermissionsPage.php:219-233` — `updateRoleUsers()` shows the team-scoped pattern
  - Spatie Permission docs: `setPermissionsTeamId()` behavior

  **Acceptance Criteria**:
  - [ ] File exists
  - [ ] 5 test methods, all pass
  - [ ] `docker-compose exec app composer test -- --filter=CrossTenantTest` → all green
  - [ ] `vendor/bin/pint --dirty` clean

  **QA Scenarios**:
  ```
  Scenario: All CrossTenantTest tests pass
    Tool: Bash (phpunit)
    Preconditions: T1, T3 complete
    Steps:
      1. docker-compose exec app composer test -- --filter=CrossTenantTest 2>&1
    Expected Result: 5 tests, all green
    Evidence: .sisyphus/evidence/t10-cross-tenant.txt
  ```

  **Commit**: NO

---

## Final Verification Wave (MANDATORY — after ALL implementation tasks)

> 4 review agents run in PARALLEL. ALL must APPROVE. Present consolidated results to user and get explicit "okay" before completing.

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify file exists with the documented change. For each "Must NOT Have": search codebase for forbidden patterns (no `BaseAdminResource` created, no `extends BaseAdminResource` on any resource, no `FilamentShieldPlugin` enabled, no widgets added to StaffPanelProvider, no Tenancy pages discovered). Check all 4 evidence files exist. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `vendor/bin/pint --dirty --test` (must pass). Run `docker-compose exec app composer test -- --filter=StaffPanel` (must pass). Review all changed files for: empty catches, commented-out code, unused imports. Check AI slop: excessive comments, over-abstraction, generic names.
  Output: `Pint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Automated Test Run** — `deep`
  Run `docker-compose exec app composer test:fresh` from clean state. Verify zero regressions in pre-existing tests. Verify new feature tests pass. Capture output to `.sisyphus/evidence/final-test-run.txt`.
  Output: `Pre-existing [N pass/N fail] | New tests [N pass/N fail] | Regressions [NONE/LIST] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff (`git diff` against main). Verify 1:1 — everything in spec was built (no missing), nothing beyond spec was built (no creep). Check "Must NOT do" compliance. Detect cross-task contamination. Flag unaccounted changes.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Success Criteria

### Verification Commands
```bash
# All must PASS
docker-compose exec app composer test -- --filter=StaffPanel   # New tests
docker-compose exec app composer test                          # Full suite, no regressions
docker-compose exec app vendor/bin/pint --dirty --test         # Formatting
docker-compose exec app php artisan route:list --path=staff    # Routes registered
```

### Final Checklist
- [ ] All "Must Have" present (1 migration, 1 refactored policy, 11 new policies, 4 admin-only overrides, 3 cluster overrides, ~10 page overrides, 1 provider update, 1 test base, 2 test files)
- [ ] All "Must NOT Have" absent (no BaseAdminResource, no resource inheritance changes, no Staff resource mirrors, no Shield, no widgets, no Tenancy pages)
- [ ] All tests pass
- [ ] `pint --dirty` clean
- [ ] Backward-compat migration ran cleanly
