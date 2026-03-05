# Super Admin Login Activity Monitoring

## TL;DR

> **Quick Summary**: Create a super admin panel feature to track login activity for gym staff (gym owners, managers, trainers, receptionists) with detailed logging and aggregate statistics.
> 
> **Deliverables**:
> - `UserLoginLog` model with database migration
> - Login event listener to capture logins automatically
> - `LoginActivityService` for analytics queries
> - New Filament page under Monitoring group with stats + table
> 
> **Estimated Effort**: Short | **Parallel Execution**: NO | **Critical Path**: Migration → Model → Listener → Service → Page

---

## Context

### Original Request
In the super admin panel, the super admin can monitor gym owners, trainers, and receptionists — tracking who logged in, when, and how many times.

### Interview Summary
**Key Discussions**:
- **Scope**: All Staff Roles (gym_owner, manager, receptionist, trainer)
- **Detail Level**: Detailed (IP address, user agent, device info)
- **UI Location**: New page under "Monitoring" navigation group
- **Stats**: Counts + Timeline (daily/weekly/monthly login counts)

**Research Findings**:
- User model uses Spatie's `HasRoles` trait for roles
- Roles defined in `DefaultRole` enum: super_admin, admin, gym_owner, manager, receptionist, trainer, gym_member
- `gym_user` pivot table stores user-gym associations with `role` column
- **No existing login tracking** - must create from scratch
- Laravel Auth events: `Illuminate\Auth\Events\Login`, `Logout`, `Failed`
- Existing Filament tests follow pattern in `tests/Feature/Filament/`

---

## Work Objectives

### Core Objective
Enable super admins to monitor login activity of gym staff (gym owners, managers, trainers, receptionists) with detailed tracking and aggregate statistics.

### Concrete Deliverables
- Database migration: `create_user_login_logs_table.php`
- Model: `app/Models/UserLoginLog.php`
- Event Listener: `app/Listeners/LogUserLogin.php`
- Service: `app/Services/LoginActivityService.php`
- Service Provider registration
- Filament Page: `app/Filament/SuperAdmin/Pages/LoginActivity.php`
- Filament Widgets: Stats cards for login counts

### Definition of Done
- [ ] Super admin can view login activity page at `/super-admin/login-activity`
- [ ] Page shows stats: total logins today, this week, this month, all time
- [ ] Table shows detailed login history with filters
- [ ] Logins are automatically captured when users authenticate
- [ ] Filter by role, gym, date range works correctly

### Must Have
- Automatic login capture via listener
- Detailed Laravel event logging: IP, user agent, device type
- Aggregate statistics by time period
- Filtering by role and gym
- Clean Filament UI matching existing patterns

### Must NOT Have (Guardrails)
- DO NOT track super_admin or admin logins
- DO NOT track gym_member logins (different system)
- DO NOT slow down authentication (async logging)
- NO real-time notifications - just passive monitoring

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: Tests-after
- **Framework**: PHPUnit
- **Test Files**: `tests/Feature/Filament/LoginActivityTest.php`

### QA Policy
Every task MUST include agent-executed QA scenarios. See TODO templates below.

---

## Execution Strategy

### Sequential Execution (Small Task - No Parallel Waves)

```
Login Activity Implementation:
├── Task 1: Migration + Model
├── Task 2: Event Listener  
├── Task 3: LoginActivityService
├── Task 4: Filament Page + Widgets
└── Task 5: Tests
```

---

## TODOs

- [ ] 1. **Create Migration + UserLoginLog Model**

  **What to do**:
  - Create migration: `database/migrations/{timestamp}_create_user_login_logs_table.php`
  - Fields: id, user_id (FK), gym_id (FK), login_at, ip_address, user_agent, device_type (nullable), login_method (default: password), created_at, updated_at
  - Indexes: user_id, login_at, (user_id, login_at) composite
  - Create model: `app/Models/UserLoginLog.php`
  - Relationships: belongsTo User, belongsTo Gym

  **Must NOT do**:
  - Add fields beyond what's needed for the feature

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocks**: Tasks 2, 3, 4

  **References**:
  - `database/migrations/2025_06_29_070713_create_gym_user_table.php` - Migration pattern
  - `app/Models/User.php` - Model structure with relationships
  - `app/Models/EmployeeDetail.php` - Simple model example

  **Acceptance Criteria**:
  - [ ] Migration runs successfully: `php artisan migrate`
  - [ ] Model exists and has correct relationships

  **QA Scenarios**:
  ```
  Scenario: Migration runs successfully
    Tool: Bash
    Preconditions: Database is accessible
    Steps:
      1. Run: php artisan migrate
    Expected Result: Table created with no errors
    Evidence: .sisyphus/evidence/task-1-migration.txt

  Scenario: Model relationships work
    Tool: Bash (tinker)
    Preconditions: Migration ran
    Steps:
      1. Run: php artisan tinker
      2. Execute: App\Models\UserLoginLog::class
    Expected Result: Model class loads without errors
    Evidence: .sisyphus/evidence/task-1-model.txt
  ```

  **Commit**: YES
  - Message: `feat: add user_login_logs migration and model`

- [ ] 2. **Create Login Event Listener**

  **What to do**:
  - Create listener: `app/Listeners/LogUserLogin.php`
  - Listen to: `Illuminate\Auth\Events\Login`
  - Capture: user, request (ip, user_agent), guard
  - Determine device_type from user_agent (mobile, desktop, tablet, bot)
  - Get user's gym from gym_user pivot (may have multiple, log all or primary)
  - Handle failures too with `Illuminate\Auth\Events\Failed`

  **Must NOT do**:
  - Block the login process - must be fire-and-forget or queued

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 1

  **References**:
  - Laravel docs: Authentication Events
  - `app/Models/User.php` - For user-gym relationship
  - `app/Providers/EventServiceProvider.php` - To register listeners (or use Event::listen in boot)

  **Acceptance Criteria**:
  - [ ] Listener captures login events
  - [ ] Logs are written to database on login

  **QA Scenarios**:
  ```
  Scenario: Login event is captured
    Tool: Bash
    Preconditions: Task 1 complete, user exists
    Steps:
      1. Create test user with gym_owner role
      2. Log in via web guard
      3. Check user_login_logs table
    Expected Record: New log entry with user_id, ip, user_agent
    Evidence: .sisyphus/evidence/task-2-login-capture.txt
  ```

  **Commit**: YES
  - Message: `feat: add login event listener for activity tracking`

- [ ] 3. **Create LoginActivityService**

  **What to do**:
  - Create service: `app/Services/LoginActivityService.php`
  - Methods:
    - `getLoginsForUser(int $userId, ?DateTime $from = null, ?DateTime $to = null)`
    - `getLoginStats(int $userId): array` (today, week, month, all-time counts)
    - `getLoginsForGym(int $gymId, ?DateTime $from, ?DateTime $to)`
    - `getLoginsByRole(string $role, ?DateTime $from, ?DateTime $to)`
    - `getRecentLogins(int $limit = 50, ?array $filters = null)`
    - `getAggregateStats(?DateTime $from, ?DateTime $to): array`

  **Must NOT do**:
  - Include super_admin or admin in queries

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 1, 2

  **References**:
  - `app/Services/SubscriptionService.php` - Service pattern example

  **Acceptance Criteria**:
  - [ ] Service methods return correct data
  - [ ] Filters exclude non-staff roles

  **QA Scenarios**:
  ```
  Scenario: Service returns login stats
    Tool: Bash (tinker)
    Preconditions: Task 2 captured logins
    Steps:
      1. php artisan tinker
      2. $svc = app(\App\Services\LoginActivityService::class)
      3. $svc->getLoginStats(1)
    Expected Result: Array with today, week, month, all counts
    Evidence: .sisyphus/evidence/task-3-service.txt
  ```

  **Commit**: YES
  - Message: `feat: add LoginActivityService for analytics`

- [ ] 4. **Create Filament LoginActivity Page**

  **What to do**:
  - Create page: `app/Filament/SuperAdmin/Pages/LoginActivity.php`
  - Add to navigation: "Monitoring" group with icon `heroicon-o-chart-bar`
  - Include:
    - Stats widgets: Cards showing logins today/week/month/all-time
    - Filter form: Role select, Gym select, Date range picker
    - Table: User, Gym, Login At, IP, Device, Actions (view details)
  - Register page in SuperAdminPanelProvider

  **Must NOT do**:
  - Duplicate existing widget patterns

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: [`fluxui-development`, `volt-development`, `tailwindcss-development`]
  - `fluxui-development`: For table components, filters
  - `tailwindcss-development`: For widget styling

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 3

  **References**:
  - `app/Filament/SuperAdmin/Resources/UserResource.php` - Table and form patterns
  - `app/Filament/Admin/Widgets/CRMStatsWidget.php` - Stats widget example
  - `app/Providers/Filament/SuperAdminPanelProvider.php` - Navigation groups

  **Acceptance Criteria**:
  - [ ] Page accessible at /super-admin/login-activity
  - [ ] Stats cards show correct counts
  - [ ] Table shows login history
  - [ ] Filters work

  **QA Scenarios**:
  ```
  Scenario: Page loads with stats
    Tool: Playwright (with fluxui-development skill)
    Preconditions: Tasks 1-3 complete
    Steps:
      1. Navigate to /super-admin/login-activity
      2. Wait for page load
      3. Check stats cards present
      4. Check table has data
    Expected Result: Page loads, shows stats, no errors
    Evidence: .sisyphus/evidence/task-4-page.png

  Scenario: Filters work
    Tool: Playwright
    Steps:
      1. Select "gym_owner" role filter
      2. Click apply
      3. Verify table updates
    Expected Result: Filtered results shown
    Evidence: .sisyphus/evidence/task-4-filters.png
  ```

  **Commit**: YES
  - Message: `feat: add login activity page to super admin`

- [ ] 5. **Create Tests**

  **What to do**:
  - Create: `tests/Feature/Filament/LoginActivityTest.php`
  - Test cases:
    - Page loads for authorized user
    - Stats display correctly
    - Table shows login history
    - Filters work (role, gym, date)
    - Unauthorized access denied

  **Must NOT do**:
  - Break existing tests

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 4

  **References**:
  - `tests/Feature/Filament/SuperAdmin/AssignPlanTest.php` - Test patterns

  **Acceptance Criteria**:
  - [ ] Tests pass: `php artisan test --filter=LoginActivityTest`

  **Commit**: YES
  - Message: `test: add login activity feature tests`

---

## Final Verification Wave

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. Verify all tasks completed.

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run: `php artisan test`, check for errors, lint issues.

- [ ] F3. **Manual QA** — `unspecified-high` (+ Playwright)
  Test the feature end-to-end: login as staff, check logs appear.

---

## Commit Strategy

- **1**: `feat: add user_login_logs migration and model`
- **2**: `feat: add login event listener for activity tracking`
- **3**: `feat: add LoginActivityService for analytics`
- **4**: `feat: add login activity page to super admin`
- **5**: `test: add login activity feature tests`

---

## Success Criteria

### Verification Commands
```bash
php artisan migrate  # Table created
php artisan test --filter=LoginActivityTest  # Tests pass
```

### Final Checklist
- [ ] All 5 tasks completed
- [ ] Tests pass
- [ ] Page accessible in super admin
- [ ] Stats and table display correctly
