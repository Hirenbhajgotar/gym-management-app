# WhatsApp Template Import Command & Queue Job

## TL;DR

> **Quick Summary**: Create an artisan command that imports WhatsApp templates from Meta/Twilio APIs for all tenants, dispatching queue jobs per gym for async processing. Templates are imported as DRAFT status (no auto-submit) to allow manual review before submission.

> **Deliverables**:
> - `app/Console/Commands/WhatsApp/ImportTenantWhatsAppTemplates.php` — Main command
> - `app/Jobs/ImportWhatsAppTemplatesJob.php` — Queue job per gym
> - `tests/Unit/Jobs/ImportWhatsAppTemplatesJobTest.php` — Unit tests
> - `tests/Feature/Console/Commands/ImportTenantWhatsAppTemplatesTest.php` — Feature tests

> **Estimated Effort**: Medium
> **Parallel Execution**: NO - sequential (command → job dispatch → job execution)
> **Critical Path**: Command → Job → Template DB sync → Tests

---

## Context

### Original Request
Create a plan to import WhatsApp templates from Meta WhatsApp and Twilio when a tenant manually sets up their WhatsApp account. When a tenant has an existing Meta/Twilio account and wants to reconnect in our system, we need to import their WhatsApp templates too.

### Interview Summary
**Key Decisions**:
- **Provider Scope**: Both Meta and Twilio (auto-detect via `provider_type`)
- **Import Behavior**: Templates imported as DRAFT only (no auto-submit for approval)
- **Execution Mode**: Queue job per gym (async processing to prevent timeouts)
- **DRY Principle**: Reuse existing `TwilioTemplateService::getTemplates()` and `MetaTemplateService::getTemplates()` methods

**User Preferences**:
- Detailed explanations with thorough context and reasoning
- Use Docker for artisan commands: `docker-compose exec app php artisan ...`
- Run Pint formatter before finalizing: `vendor/bin/pint --dirty`

### Research Findings

**1. Existing Template Services**:
- `TwilioTemplateService::getTemplates(int $gymId)` → returns `TemplateCollectionResult`
- `MetaTemplateService::getTemplates(int $gymId)` → returns `TemplateCollectionResult`
- Both already implement tenant isolation via `gymId` parameter

**2. Tenant Isolation Pattern**:
- `WhatsAppAccount` links to `gym_id` (tenant)
- `WhatsAppTemplate` links to `gym_id` (tenant)
- Services scope data by `gymId`

**3. Existing Job Pattern** (`ProcessMetaTemplateWebhook`):
```php
class ProcessMetaTemplateWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;
    
    public function uniqueId(): string { ... }
    public function handle(): void { ... }
    public function failed(\Throwable $exception): void { ... }
}
```

**4. WhatsAppTemplateManager Pattern** (existing sync method):
- Uses `updateOrCreate()` for upsert logic
- Sets `twilio_template_sid` and `meta_template_id` directly
- Creates templates with `status = APPROVED` (but we need DRAFT)

---

## Work Objectives

### Core Objective
Create a command and queue job system to import WhatsApp templates from Meta/Twilio for all tenants when they reconnect their accounts.

### Concrete Deliverables
- [ ] New command: `whatsapp:import-templates {--gym=}` — import templates for specific gym or all gyms
- [ ] New job: `ImportWhatsAppTemplatesJob` — async template import per gym
- [ ] Unit tests for the job
- [ ] Feature tests for the command

### Definition of Done
- [ ] `php artisan whatsapp:import-templates` runs successfully
- [ ] `php artisan whatsapp:import-templates --gym=1` runs for specific gym
- [ ] Templates imported as DRAFT status (not auto-submitted)
- [ ] Job dispatches to queue (not synchronous)
- [ ] Both Meta and Twilio providers supported
- [ ] Tests pass

### Must Have
- Tenant isolation (templates linked to correct `gym_id`)
- Both Meta and Twilio provider support
- Async queue job execution
- Proper error handling and logging
- Unique job keys to prevent duplicate processing

### Must NOT Have
- Auto-submission of imported templates for approval
- Template creation on remote providers (import only)
- Direct API calls (reuse existing services)

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: YES (Tests-after)
- **Framework**: PHPUnit

### QA Policy
Every task includes agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/`.

- **CLI/Command**: Use Bash (artisan commands) - Run command, capture output, verify exit code
- **Job**: Use Bash (queue worker) - Dispatch job, process queue, verify database state

---

## Execution Strategy

### Sequential Flow

```
Command (whatsapp:import-templates)
    ↓
Query gyms with WhatsApp accounts
    ↓
Dispatch ImportWhatsAppTemplatesJob per gym
    ↓
ImportWhatsAppTemplatesJob::handle()
    ↓
Determine provider (WABA or TWILIO)
    ↓
Call appropriate service (MetaTemplateService or TwilioTemplateService)
    ↓
Save templates to database as DRAFT
```

### Dependency Matrix
- **Command**: No dependencies (can start immediately)
- **Job**: Depends on command dispatch
- **Tests**: Depend on command and job

---

## TODOs

- [ ] 1. Create `ImportWhatsAppTemplatesJob` queue job

  **What to do**:
  - Create `app/Jobs/ImportWhatsAppTemplatesJob.php`
  - Implement `ShouldQueue` with queue name `whatsapp-templates`
  - Constructor takes `int $gymId, ?string $providerType = null`
  - `handle()` method:
    1. Get `WhatsAppAccount` for gym via `gym_id`
    2. Determine provider (use account's `provider_type` or injected override)
    3. For Twilio: Use `TwilioTemplateService` with gymId context → call `getTemplates()`
    4. For Meta: Use `MetaTemplateService` with gymId context → call `getTemplates()`
    5. For each template from API:
       - `updateOrCreate` by `name` (friendly_name for Twilio, name for Meta)
       - Set `gym_id`, `status = DRAFT`, `is_active = true`
       - Set provider-specific ID (`twilio_template_sid` or `meta_template_id`)
       - Set `display_name`, `body`, `category`, `variables`
    6. Log success/failure with gym_id
  - Implement `ShouldBeUnique` with key `import-whatsapp-templates:gym:{gymId}`
  - Implement `failed()` for error logging
  - Set `$tries = 3`, `$backoff = 60`, `$timeout = 180`

  **Must NOT do**:
  - Do NOT auto-submit templates for approval (that's a separate workflow)
  - Do NOT create templates remotely (only import from remote to local)
  - Do NOT skip tenant isolation

  **Recommended Agent Profile**:
  - **Category**: `laravel-specialist`
    - Reason: Job needs Laravel queue patterns, Eloquent models, service injection
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Queue job structure, ShouldQueue interface

  **Parallelization**:
  - **Can Run In Parallel**: YES (multiple gym imports can run concurrently)
  - **Parallel Group**: Wave 2 (with command)
  - **Blocks**: Tests
  - **Blocked By**: None (foundation task)

  **References**:
  - `app/Jobs/ProcessMetaTemplateWebhook.php:30-58` — Job structure pattern with ShouldBeUnique, ShouldQueue, constructor params, handle() method
  - `app/Services/WhatsAppTemplateManager.php:148-203` — `syncTemplatesToDatabase()` upsert logic with `updateOrCreate`
  - `app/Services/Twilio/TwilioTemplateService.php:188-216` — `getTemplates()` returns TemplateCollectionResult
  - `app/Services/Meta/MetaTemplateService.php:177-204` — `getTemplates()` returns TemplateCollectionResult
  - `app/Enums/WhatsAppProviderTypeEnum.php` — WABA and TWILIO cases

  **Acceptance Criteria**:

  **QA Scenarios**:

  ```
  Scenario: Happy path - Twilio template import
    Tool: Bash
    Preconditions: Gym #1 has active Twilio account with credentials
    Steps:
      1. Dispatch job: ImportWhatsAppTemplatesJob::dispatch(1)
      2. Process queue: docker-compose exec app php artisan queue:work --once
      3. Query DB: SELECT * FROM whats_app_templates WHERE gym_id = 1 AND status = 'draft'
    Expected Result: At least 1 template imported as DRAFT with twilio_template_sid set
    Evidence: .sisyphus/evidence/task-1-twilio-import.txt

  Scenario: Happy path - Meta template import
    Tool: Bash
    Preconditions: Gym #2 has active Meta WABA account with credentials
    Steps:
      1. Dispatch job: ImportWhatsAppTemplatesJob::dispatch(2)
      2. Process queue
      3. Query DB: SELECT * FROM whats_app_templates WHERE gym_id = 2 AND status = 'draft'
    Expected Result: At least 1 template imported as DRAFT with meta_template_id set
    Evidence: .sisyphus/evidence/task-1-meta-import.txt

  Scenario: Failure - No WhatsApp account for gym
    Tool: Bash
    Preconditions: Gym #99 has no WhatsApp account
    Steps:
      1. Dispatch job: ImportWhatsAppTemplatesJob::dispatch(99)
      2. Process queue
      3. Check job logs for warning
    Expected Result: Job logs warning, no templates created, no error thrown
    Evidence: .sisyphus/evidence/task-1-no-account.txt
  ```

  **Commit**: YES
  - Message: `feat(whatsapp): add ImportWhatsAppTemplatesJob queue job`
  - Files: `app/Jobs/ImportWhatsAppTemplatesJob.php`
  - Pre-commit: PHP syntax check

- [ ] 2. Create `ImportTenantWhatsAppTemplates` artisan command

  **What to do**:
  - Create `app/Console/Commands/WhatsApp/ImportTenantWhatsAppTemplates.php`
  - Signature: `whatsapp:import-templates {--gym= : Import templates for specific gym ID} {--sync : Run synchronously instead of dispatching jobs}`
  - `handle()` method:
    1. Get `--gym` option (null means all gyms)
    2. If `--gym` provided: query single gym, dispatch single job
    3. If no `--gym`: query all gyms with WhatsApp accounts, dispatch jobs per gym
    4. If `--sync`: call job handle() directly instead of dispatching
    5. Output progress: "Dispatching import for Gym X..." with counts
  - Show deprecation warning for `--sync` (async is recommended)

  **Must NOT do**:
  - Do NOT run imports synchronously by default (use queue for scalability)
  - Do NOT skip gym validation

  **Recommended Agent Profile**:
  - **Category**: `laravel-specialist`
    - Reason: Laravel artisan command with options
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Command structure, option handling

  **Parallelization**:
  - **Can Run In Parallel**: YES (command just dispatches jobs)
  - **Parallel Group**: Wave 2 (with job)
  - **Blocks**: Tests
  - **Blocked By**: None (can start immediately, depends on job structure)

  **References**:
  - `app/Console/Commands/SyncWhatsAppTemplates.php:41-58` — Iterate gyms with WhatsApp accounts via `Gym::whereHas()`
  - `app/Console/Commands/WhatsApp/ImportWhatsAppTemplates.php:21` — Existing command signature pattern

  **Acceptance Criteria**:

  **QA Scenarios**:

  ```
  Scenario: Help output
    Tool: Bash
    Preconditions: None
    Steps:
      1. Run: docker-compose exec app php artisan whatsapp:import-templates --help
    Expected Result: Shows signature with --gym and --sync options
    Evidence: .sisyphus/evidence/task-2-help.txt

  Scenario: Single gym import
    Tool: Bash
    Preconditions: Gym #1 exists
    Steps:
      1. Run: docker-compose exec app php artisan whatsapp:import-templates --gym=1
      2. Check output: "Dispatching import for Gym ID: 1"
    Expected Result: Job dispatched for gym 1
    Evidence: .sisyphus/evidence/task-2-single-gym.txt

  Scenario: All gyms import
    Tool: Bash
    Preconditions: Multiple gyms with WhatsApp accounts
    Steps:
      1. Run: docker-compose exec app php artisan whatsapp:import-templates
      2. Check output: Shows dispatch count
    Expected Result: Jobs dispatched for all gyms with WhatsApp accounts
    Evidence: .sisyphus/evidence/task-2-all-gyms.txt
  ```

  **Commit**: YES
  - Message: `feat(whatsapp): add ImportTenantWhatsAppTemplates command`
  - Files: `app/Console/Commands/WhatsApp/ImportTenantWhatsAppTemplates.php`
  - Pre-commit: PHP syntax check

- [ ] 3. Write unit tests for `ImportWhatsAppTemplatesJob`

  **What to do**:
  - Create `tests/Unit/Jobs/ImportWhatsAppTemplatesJobTest.php`
  - Test cases:
    1. `test_job_requires_gym_id` - verify constructor
    2. `test_job_is_unique_per_gym` - verify ShouldBeUnique implementation
    3. `test_handles_twilio_provider` - mock TwilioTemplateService, verify getTemplates called
    4. `test_handles_meta_provider` - mock MetaTemplateService, verify getTemplates called
    5. `test_imports_templates_as_draft` - verify templates saved with DRAFT status
    6. `test_upserts_existing_templates` - verify updateOrCreate behavior
    7. `test_handles_no_account` - verify graceful handling when no WhatsApp account
    8. `test_handles_api_failure` - verify error logging when API fails
    9. `test_sets_provider_specific_ids` - verify twilio_template_sid/meta_template_id set
    10. `test_failed_method_logs_error` - verify failed() error logging

  **Must NOT do**:
  - Do NOT test command output (that's feature test territory)
  - Do NOT mock database (use RefreshDatabase)

  **Recommended Agent Profile**:
  - **Category**: `laravel-specialist`
    - Reason: PHPUnit tests with mocking
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Unit testing, mock patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES (independent test file)
  - **Parallel Group**: Wave 3 (after job and command)
  - **Blocks**: None
  - **Blocked By**: Job implementation (tests need job to exist)

  **References**:
  - `tests/Unit/Jobs/ProcessMetaTemplateWebhookTest.php` — Existing job test patterns
  - `tests\Unit\Jobs\ProcessTwilioTemplateWebhookTest.php` — Twilio job test patterns
  - `app/Models/WhatsAppTemplate.php:72-90` — Model fillable/factory structure

  **Acceptance Criteria**:
  - [ ] Test file created: tests/Unit/Jobs/ImportWhatsAppTemplatesJobTest.php
  - [ ] `docker-compose exec app php artisan test --filter=ImportWhatsAppTemplatesJobTest` → PASS

  **QA Scenarios**:

  ```
  Scenario: Job test - template import as DRAFT
    Tool: Bash
    Preconditions: Gym with Twilio account, 2 templates in Twilio API
    Steps:
      1. Mock TwilioTemplateService returning 2 templates
      2. Run: docker-compose exec app php artisan test tests/Unit/Jobs/ImportWhatsAppTemplatesJobTest.php
    Expected Result: 10 tests pass, templates in DB as DRAFT status
    Evidence: .sisyphus/evidence/task-3-test-results.txt
  ```

  **Commit**: YES
  - Message: `test(whatsapp): add ImportWhatsAppTemplatesJob unit tests`
  - Files: `tests/Unit/Jobs/ImportWhatsAppTemplatesJobTest.php`
  - Pre-commit: Test passes

- [ ] 4. Write feature tests for `ImportTenantWhatsAppTemplates` command

  **What to do**:
  - Create `tests/Feature/Console/Commands/ImportTenantWhatsAppTemplatesTest.php`
  - Test cases:
    1. `test_command_exists` - verify command is registered
    2. `test_help_shows_options` - verify --help output
    3. `test_imports_single_gym` - dispatch job for specific gym
    4. `test_imports_all_gyms` - dispatch jobs for all gyms with accounts
    5. `test_sync_mode_runs_job_directly` - --sync flag processes synchronously
    6. `test_shows_dispatch_count` - verify output shows count of dispatched jobs

  **Must NOT do**:
  - Do NOT test job internals (that's unit test territory)
  - Do NOT test database state (that's job test territory)

  **Recommended Agent Profile**:
  - **Category**: `laravel-specialist`
    - Reason: PHPUnit feature tests
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Feature testing, command testing

  **Parallelization**:
  - **Can Run In Parallel**: YES (independent test file)
  - **Parallel Group**: Wave 3 (with unit tests)
  - **Blocks**: None
  - **Blocked By**: Command implementation (tests need command to exist)

  **References**:
  - `tests/Feature/Console/Commands/ExampleTest.php` — Existing command test patterns
  - `app/Console/Commands/WhatsApp/ImportWhatsAppTemplates.php` — Existing command for reference

  **Acceptance Criteria**:
  - [ ] Test file created: tests/Feature/Console/Commands/ImportTenantWhatsAppTemplatesTest.php
  - [ ] `docker-compose exec app php artisan test --filter=ImportTenantWhatsAppTemplatesTest` → PASS

  **QA Scenarios**:

  ```
  Scenario: Command feature test - single gym
    Tool: Bash
    Preconditions: Gym #1 with WhatsApp account
    Steps:
      1. Run: docker-compose exec app php artisan test tests/Feature/Console/Commands/ImportTenantWhatsAppTemplatesTest.php
    Expected Result: 6 tests pass
    Evidence: .sisyphus/evidence/task-4-test-results.txt
  ```

  **Commit**: YES
  - Message: `test(whatsapp): add ImportTenantWhatsAppTemplates command tests`
  - Files: `tests/Feature/Console/Commands/ImportTenantWhatsAppTemplatesTest.php`
  - Pre-commit: Test passes

- [ ] 5. Run Pint formatter and verify clean code

  **What to do**:
  - Run: `vendor/bin/pint --dirty --format agent` on all changed files
  - Verify: Output shows `{"result":"pass"}`
  - Run: LSP diagnostics on all new files
  - Verify: No errors

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Formatting verification only

  **Parallelization**:
  - **Can Run In Parallel**: NO (final step)
  - **Blocked By**: All implementation and tests complete

  **References**:
  - Laravel Pint configuration: `vendor/bin/pint`
  - AGENTS.md: Run Pint formatter before finalizing

---

## Final Verification Wave

- [ ] F1. **Plan Compliance Audit** — `oracle`
- [ ] F2. **Code Quality Review** — `unspecified-high`
- [ ] F3. **CLI Verification** — Run command with different options, verify output
- [ ] F4. **Scope Fidelity Check** — Verify all features implemented, no scope creep

---

## Commit Strategy

- `feat(whatsapp): add template import command and queue job` — new files + tests

---

## Success Criteria

### Verification Commands
```bash
# Test command help
docker-compose exec app php artisan whatsapp:import-templates --help

# Test for specific gym
docker-compose exec app php artisan whatsapp:import-templates --gym=1

# Test dispatching jobs
docker-compose exec app php artisan whatsapp:import-templates --gym=1 --sync

# Run tests
docker-compose exec app php artisan test --filter=ImportWhatsAppTemplatesJobTest
docker-compose exec app php artisan test --filter=ImportTenantWhatsAppTemplatesTest
```

### Final Checklist
- [ ] Command signature matches: `whatsapp:import-templates {--gym=}`
- [ ] Templates imported as DRAFT status
- [ ] Both Meta and Twilio supported
- [ ] Async job dispatch working
- [ ] Unit tests cover job logic
- [ ] Feature tests cover command behavior