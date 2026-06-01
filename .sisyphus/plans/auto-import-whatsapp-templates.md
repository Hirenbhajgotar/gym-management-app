# Plan: Auto-Import WhatsApp Templates on Manual WABA Registration

## TL;DR

> **Quick Summary**: Automatically dispatch `ImportWhatsAppTemplatesJob` when a gym owner manually connects their WhatsApp Business Account (WABA) via the setup wizard, so existing Meta templates are imported as DRAFT records without requiring manual CLI commands.

> **Deliverables**:
> - Static `dispatchForGym()` method added to `ImportWhatsAppTemplatesJob` (DRY dispatch helper)
> - `ConnectExistingWabaAction` dispatches import job after successful manual WABA connection
> - `EmbeddedSignupController::manualConnect()` dispatches import job after successful manual connect
> - Feature tests verifying job dispatch from both trigger points
> - Unit test for `dispatchForGym()` static method

> **Estimated Effort**: Short (4-6 tasks across 3 waves)
> **Parallel Execution**: YES — Wave 1 then Wave 2 (parallel)
> **Critical Path**: T2 → T3 (tests depend on T1 code)

---

## Context

### Original Request
> "Create a plan to imports whatsapp templates when user register their account manually. (Only imports its when user register his/her account manually)."

### Interview Summary

**Flow Analyzed**:
- User manually connects WABA via 2 paths:
  1. **Primary**: `ConnectExistingWabaAction` (Filament wizard, `WabaAccountStep` → manual_connect → `afterValidation`)
  2. **Secondary**: `EmbeddedSignupController::manualConnect()` (HTTP POST route, separate entry point)
- Both call `MetaWhatsAppProvider::connectManualWaba()` → `MetaWabaService::connectManualWaba()` → creates `WhatsAppAccount` (status=ACTIVE, verification_status=VERIFIED) + `WhatsAppPhoneNumber` records
- Existing `ImportWhatsAppTemplatesJob` already handles fetching templates from Meta API and upserting to DB as DRAFT
- The job checks `isActive() && isVerified()` — both are satisfied immediately after manual connect

**Trigger Points**:
| File | Line | Trigger | Current Flow |
|------|------|---------|-------------|
| `ConnectExistingWabaAction.php` | 43-45 | `$result['success']` | Shows success notification → user proceeds to next wizard step |
| `EmbeddedSignupController.php` | 122-130 | `$result['success']` | Redirects to setup page with success flash |

**Explicitly Excluded**:
- Embedded signup callback (`EmbeddedSignupController@callback`) — user said "Only imports when manually registered"
- Twilio account setup — different provider, not manual WABA

### Research Findings

- `ImportWhatsAppTemplatesJob` constructor: `(int $gymId, ?WhatsAppProviderTypeEnum $providerType = null)`
- Job uses `ShouldBeUnique` (key: `"import-whatsapp-templates:gym:{$gymId}"`) — safe for multiple dispatches
- Job has `$tries = 3`, `$backoff = 60`, `$timeout = 180`, queue: `whatsapp-templates`
- Manual connect sets `verified_at = now()` — account immediately satisfies `isActive() && isVerified()`
- When no templates exist on Meta, `getTemplates()` returns `TemplateCollectionResult(success: true, templates: [])` — sync loop runs 0 times, no errors

### Metis Review
> Metis consultation timed out. Self-review performed:
> - **Q1**: Should embedded signup ALSO trigger import? → NO, explicitly excluded per user
> - **Q2**: Should we add a `$dontImportTemplates` flag to allow skipping? → Unnecessary complexity for now
> - **Q3**: What if queue worker isn't running? → Job is queued; import happens when worker starts. Acceptable.

---

## Work Objectives

### Core Objective
Automatically dispatch the template import job at both manual WABA connection trigger points, using a DRY dispatch helper.

### Concrete Deliverables
- [x] Static `ImportWhatsAppTemplatesJob::dispatchForGym()` added (DRY wrapper)
- [x] `ConnectExistingWabaAction` dispatches job after successful connect
- [x] `EmbeddedSignupController::manualConnect()` dispatches job after successful connect
- [x] Feature tests with `Queue::fake()` for both trigger points
- [x] Unit test for `dispatchForGym()` static method

### Definition of Done
- [ ] `vendor/bin/pint --dirty` passes on all changed files
- [ ] `php artisan test --filter=ImportWhatsAppTemplates` all pass
- [ ] Queue fake assertions confirm job dispatched for correct gym
- [ ] No regressions in manual WABA connection flow

### Must Have
- Dispatch `ImportWhatsAppTemplatesJob` after successful `connectManualWaba()` call in BOTH trigger points
- DRY dispatch logic — no duplicated dispatch code between the two trigger points

### Must NOT Have (Guardrails)
- NO changes to embedded signup callback flow
- NO changes to Twilio setup flow
- NO new models, migrations, or config keys
- NO changes to `ImportWhatsAppTemplatesJob::handle()` logic
- NO new events or listeners (unnecessary complexity for this scope)

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit, RefreshDatabase)
- **Automated tests**: YES (tests-after)
- **Framework**: PHPUnit

### QA Policy
Every task MUST include agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **Job dispatch verification**: Queue::fake() to assert job pushed
- **Static method test**: Verify job constructor + queue assignment
- **Integration test**: Full manual connect flow with mock provider

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation — sequential, 2 tasks):
├── T1: Add static dispatchForGym() to ImportWhatsAppTemplatesJob [quick]
└── T2: Unit test for dispatchForGym() [quick]

Wave 2 (Integration — PARALLEL, 2 tasks):
├── T3: Modify ConnectExistingWabaAction to dispatch import [quick]
└── T4: Modify EmbeddedSignupController::manualConnect to dispatch import [quick]

Wave 3 (Tests — PARALLEL, 2 tasks):
├── T5: Feature test for ConnectExistingWabaAction dispatch [quick]
└── T6: Feature test for EmbeddedSignupController::manualConnect dispatch [quick]

Wave FINAL (4 parallel reviews):
├── F1: Plan compliance audit (oracle)
├── F2: Code quality review (unspecified-high)
├── F3: Real manual QA (unspecified-high + playwright)
└── F4: Scope fidelity check (deep)
→ Present results → Get explicit user okay

Critical Path: T1 → T2 → T3+T4 → T5+T6 → F1-F4 → user okay
Parallel Speedup: ~50% faster than sequential
Max Concurrent: 2 (Waves 2 & 3)
```

### Dependency Matrix
- **T1**: - → T2, T3, T4, T5, T6
- **T2**: T1 → - (test coverage of T1)
- **T3**: T1 → T5
- **T4**: T1 → T6
- **T5**: T3 → - (verifies T3)
- **T6**: T4 → - (verifies T4)
- **F1-F4**: T1-T6 → user

### Agent Dispatch Summary
- **Wave 1**: 2 tasks — T1→`quick`, T2→`quick`
- **Wave 2**: 2 tasks — T3→`quick`, T4→`quick`
- **Wave 3**: 2 tasks — T5→`quick`, T6→`quick`
- **Wave FINAL**: 4 tasks — F1→`oracle`, F2→`unspecified-high`, F3→`unspecified-high`, F4→`deep`

---

## TODOs

- [ ] 1. Add static `dispatchForGym()` method to `ImportWhatsAppTemplatesJob`

  **What to do**:
  - Add a public static method `dispatchForGym(int $gymId, ?WhatsAppProviderTypeEnum $providerType = null): void` to `ImportWhatsAppTemplatesJob`
  - Method should: create a new instance, set the `whatsapp-templates` queue via `onQueue()`, and dispatch it
  - Follow the same pattern used in `ImportTenantWhatsAppTemplates` command (lines 97-99) and `ImportWhatsAppTemplates` command (lines 62-64)
  - Use the existing `Dispatchable` trait (already imported via `use Dispatchable`)
  - Add PHPDoc block explaining purpose: "DRY dispatcher for manual WABA registration flow"
  - This is the DRY core — both trigger points (T3, T4) will call this instead of duplicating queue logic

  **Must NOT do**:
  - Do NOT modify `handle()` method logic
  - Do NOT change constructor signature
  - Do NOT remove `ShouldBeUnique` or other existing traits

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Single-method addition to existing class, trivial logic
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Queue job patterns, Dispatchable trait usage

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 1
  - **Blocks**: T2, T3, T4, T5, T6
  - **Blocked By**: None (can start immediately)

  **References**:
  - `app/Jobs/ImportWhatsAppTemplatesJob.php` — Existing job class to modify (add static method)
  - `app/Console/Commands/WhatsApp/ImportTenantWhatsAppTemplates.php:97-99` — Existing dispatch pattern to replicate (new job → onQueue → dispatch)
  - `app/Jobs/ProcessMetaTemplateWebhook.php` — Example of ShouldBeUnique + ShouldQueue job pattern in codebase

  **Acceptance Criteria**:
  - [ ] Static method `dispatchForGym(int $gymId, ?WhatsAppProviderTypeEnum $providerType = null): void` added to `ImportWhatsAppTemplatesJob`
  - [ ] Method creates job instance, sets queue to `whatsapp-templates`, and dispatches
  - [ ] Existing job behavior unchanged (handle(), uniqueId(), queue())

  **QA Scenarios**:
  ```
  Scenario: dispatchForGym creates and queues the job
    Tool: Bash (php artisan)
    Preconditions: Job class exists
    Steps:
      1. Add a temporary test: `ImportWhatsAppTemplatesJob::dispatchForGym(999, WhatsAppProviderTypeEnum::WABA);`
      2. Assert no PHP error thrown
    Expected Result: Static method call succeeds without exception
    Evidence: .sisyphus/evidence/task-1-dispatch-method.txt
  ```

  **Evidence to Capture**:
  - [ ] Verified method signature in file

  **Commit**: YES (groups with T2)
  - Message: `feat(whatsapp): add dispatchForGym static method to ImportWhatsAppTemplatesJob`
  - Files: `app/Jobs/ImportWhatsAppTemplatesJob.php`
  - Pre-commit: `php -l app/Jobs/ImportWhatsAppTemplatesJob.php`

- [ ] 2. Unit test for `dispatchForGym()` static method

  **What to do**:
  - Add a new test method to `tests/Unit/Jobs/ImportWhatsAppTemplatesJobTest.php` (or create new test file `tests/Unit/Jobs/ImportWhatsAppTemplatesDispatchTest.php`)
  - Use `Queue::fake()` to assert the job is pushed
  - Test with both `WhatsAppProviderTypeEnum::WABA` and null provider type
  - Verify the job is pushed on the `whatsapp-templates` queue
  - Test using `Queue::assertPushedOn()` for queue assertion
  - Follow the existing test patterns in `ImportWhatsAppTemplatesJobTest.php` (RefreshDatabase, Gym factory, setup/tearDown)

  **Must NOT do**:
  - Do NOT test `handle()` logic — that's already covered by existing tests
  - Do NOT modify setUp/tearDown in the existing test file

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple Queue::fake() assertion test
  - **Skills**: [`phpunit-best-practices`]
    - `phpunit-best-practices`: Queue faking assertions, test structure

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 1
  - **Blocks**: None
  - **Blocked By**: T1 (needs the method to test)

  **References**:
  - `tests/Unit/Jobs/ImportWhatsAppTemplatesJobTest.php` — Existing test file with setUp/tearDown patterns
  - `tests/Feature/ImportTenantWhatsAppTemplatesTest.php:87-97` — Example of `Queue::fake()` + assertPushed pattern
  - `app/Jobs/ImportWhatsAppTemplatesJob.php` — Job class being tested

  **Acceptance Criteria**:
  - [ ] Test `test_dispatch_for_gym_queues_job()` — asserts job is pushed
  - [ ] Test `test_dispatch_for_gym_with_provider()` — asserts job is pushed with provider type
  - [ ] Test `test_dispatch_for_gym_on_correct_queue()` — asserts queue name is `whatsapp-templates`
  - [ ] `php artisan test --compact --filter=ImportWhatsAppTemplatesJobTest` all pass

  **QA Scenarios**:
  ```
  Scenario: Unit tests pass for dispatchForGym
    Tool: Bash (php artisan)
    Preconditions: T1 implemented, test file updated
    Steps:
      1. Run: php artisan test --compact --filter=ImportWhatsAppTemplatesJobTest
      2. Check exit code is 0 (success)
    Expected Result: All tests pass
    Evidence: .sisyphus/evidence/task-2-test-results.txt

  Scenario: Queue::fake assertion confirms dispatch
    Tool: Bash (php artisan tinker)
    Preconditions: Test file correctly uses Queue::fake
    Steps:
      1. Manually verify test uses: Queue::fake()
      2. Verify test asserts: Queue::assertPushed(ImportWhatsAppTemplatesJob::class)
    Expected Result: Queue assertions are present in test
    Evidence: .sisyphus/evidence/task-2-assertions.txt
  ```

  **Evidence to Capture**:
  - [ ] Test file content snippet showing assertions
  - [ ] Test run output

  **Commit**: YES (groups with T1)
  - Message: (same commit) `feat(whatsapp): add dispatchForGym static method to ImportWhatsAppTemplatesJob`
  - Files: `app/Jobs/ImportWhatsAppTemplatesJob.php`, `tests/Unit/Jobs/ImportWhatsAppTemplatesJobTest.php`
  - Pre-commit: `php artisan test --compact --filter=ImportWhatsAppTemplatesJobTest`

- [ ] 3. Modify `ConnectExistingWabaAction` to dispatch template import after successful manual WABA connect

  **What to do**:
  - In `ConnectExistingWabaAction::__invoke()`, after the successful connection result is confirmed (line 43: `if ($result['success'])`), call `ImportWhatsAppTemplatesJob::dispatchForGym($gymId)`
  - Insert AFTER line 43 (`if ($result['success'])`) but BEFORE line 44 (`$this->page->refresh()`)
  - Use the static method from T1 (DRY)
  - Add a single-line comment: `// Import existing Meta templates as DRAFT records`
  - Import the job class at top of file if not already imported
  - This ensures templates start importing asynchronously as soon as the WABA is connected, while the user proceeds to the next wizard step

  **Must NOT do**:
  - Do NOT change the notification logic
  - Do NOT remove rate limiting, validation, or error handling
  - Do NOT make this synchronous — must stay queued
  - Do NOT log the gym ID in clear text without sanitization (follow `Log::error('WABA Connect Error', ['e' => $e])` pattern)

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Single-line method call insertion in existing action class
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Job dispatch patterns, action class conventions

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with T4)
  - **Blocks**: T5
  - **Blocked By**: T1 (needs dispatchForGym method)

  **References**:
  - `app/Filament/Admin/WhatsAppSetup/Actions/ConnectExistingWabaAction.php:38-52` — The action's __invoke() method. Success check at line 43, refresh at line 44, notification at line 45
  - `app/Jobs/ImportWhatsAppTemplatesJob.php` — The dispatchForGym method from T1
  - `app/Console/Commands/WhatsApp/ImportTenantWhatsAppTemplates.php:97-99` — Reference dispatch pattern (new job → onQueue → dispatch)

  **Acceptance Criteria**:
  - [ ] `ImportWhatsAppTemplatesJob::dispatchForGym($gymId)` called inside `if ($result['success'])` block
  - [ ] Call placed before `$this->page->refresh()`
  - [ ] No syntax errors in the file
  - [ ] `vendor/bin/pint --dirty` passes

  **QA Scenarios**:
  ```
  Scenario: Job dispatch call exists in ConnectExistingWabaAction
    Tool: Bash (grep/php -l)
    Preconditions: T1 completed, file modified
    Steps:
      1. Run: php -l app/Filament/Admin/WhatsAppSetup/Actions/ConnectExistingWabaAction.php
      2. Run: grep -n "dispatchForGym" app/Filament/Admin/WhatsAppSetup/Actions/ConnectExistingWabaAction.php
    Expected Result: PHP syntax OK, dispatchForGym found in file after line 43
    Evidence: .sisyphus/evidence/task-3-syntax-check.txt

  Scenario: Import statement exists
    Tool: Bash (grep)
    Steps:
      1. Run: grep "use App\\\\Jobs\\\\ImportWhatsAppTemplatesJob;" app/Filament/Admin/WhatsAppSetup/Actions/ConnectExistingWabaAction.php
    Expected Result: Import statement present
    Evidence: .sisyphus/evidence/task-3-import.txt
  ```

  **Evidence to Capture**:
  - [ ] PHP syntax check
  - [ ] grep output showing dispatchForGym call location
  - [ ] Pint formatting result

  **Commit**: YES (separate commit)
  - Message: `feat(whatsapp): dispatch template import after manual WABA connect action`
  - Files: `app/Filament/Admin/WhatsAppSetup/Actions/ConnectExistingWabaAction.php`
  - Pre-commit: `vendor/bin/pint --dirty`

- [ ] 4. Modify `EmbeddedSignupController::manualConnect()` to dispatch template import after successful manual connect

  **What to do**:
  - In `EmbeddedSignupController::manualConnect()`, after the successful connection result is confirmed (line 122: `if ($result['success'])`), call `ImportWhatsAppTemplatesJob::dispatchForGym($gym->id)`
  - Insert AFTER line 122 (`if (! $result['success'])`) block ends, BEFORE the redirect on line 128-130
  - The success case starts at line 128. Add the dispatch call right before the redirect statement at line 128
  - Use the static method from T1 (DRY)
  - Add a single-line comment: `// Import existing Meta templates as DRAFT records`
  - Import the job class at top of file via `use App\Jobs\ImportWhatsAppTemplatesJob;`

  **Must NOT do**:
  - Do NOT modify the error handling path (lines 123-126)
  - Do NOT change validation logic
  - Do NOT make synchronous
  - Do NOT add logging of sensitive data

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Single-line method call insertion in existing controller
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Controller patterns, job dispatch

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with T3)
  - **Blocks**: T6
  - **Blocked By**: T1 (needs dispatchForGym method)

  **References**:
  - `app/Http/Controllers/WhatsApp/EmbeddedSignupController.php:96-131` — The manualConnect() method. Error check at 122, success redirect at 128-130
  - `app/Jobs/ImportWhatsAppTemplatesJob.php` — The dispatchForGym method from T1
  - `routes/web.php:181` — Route definition: `POST /whatsapp/embedded-signup/manual-connect/{tenant}`

  **Acceptance Criteria**:
  - [ ] `ImportWhatsAppTemplatesJob::dispatchForGym($gym->id)` called in the success block
  - [ ] Call placed before the redirect, after the error check
  - [ ] `use App\Jobs\ImportWhatsAppTemplatesJob;` import added at top
  - [ ] No syntax errors
  - [ ] `vendor/bin/pint --dirty` passes

  **QA Scenarios**:
  ```
  Scenario: Job dispatch call exists in EmbeddedSignupController::manualConnect
    Tool: Bash (php -l / grep)
    Preconditions: T1 completed, file modified
    Steps:
      1. Run: php -l app/Http/Controllers/WhatsApp/EmbeddedSignupController.php
      2. Run: grep -n "dispatchForGym" app/Http/Controllers/WhatsApp/EmbeddedSignupController.php
    Expected Result: PHP syntax OK, dispatchForGym found in file after error check, before redirect
    Evidence: .sisyphus/evidence/task-4-syntax-check.txt

  Scenario: Import statement exists
    Tool: Bash (grep)
    Steps:
      1. Run: grep "use App\\\\Jobs\\\\ImportWhatsAppTemplatesJob;" app/Http/Controllers/WhatsApp/EmbeddedSignupController.php
    Expected Result: Import statement present
    Evidence: .sisyphus/evidence/task-4-import.txt
  ```

  **Evidence to Capture**:
  - [ ] PHP syntax check
  - [ ] grep output showing dispatchForGym call location
  - [ ] Pint formatting result

  **Commit**: YES (separate commit)
  - Message: `feat(whatsapp): dispatch template import after manual connect controller`
  - Files: `app/Http/Controllers/WhatsApp/EmbeddedSignupController.php`
  - Pre-commit: `vendor/bin/pint --dirty`

- [ ] 5. Feature test for `ConnectExistingWabaAction` dispatch

  **What to do**:
  - Create `tests/Feature/Filament/WhatsApp/ConnectExistingWabaActionDispatchesImportTest.php`
  - Test that when `ConnectExistingWabaAction::__invoke()` succeeds, `ImportWhatsAppTemplatesJob` is dispatched with the correct `$gymId`
  - Use `Queue::fake()` to intercept the job dispatch
  - Mock the `MetaWhatsAppProvider::connectManualWaba()` to return `['success' => true]`
  - Follow the existing test patterns in the codebase (use `RefreshDatabase`, create a `Gym` factory record)
  - Assert: `Queue::assertPushed(ImportWhatsAppTemplatesJob::class, fn ($job) => $job->gymId === $gymId)`
  - Also test the negative case: when `connectManualWaba()` returns `['success' => false]`, the job is NOT dispatched
  - Use `Str::uuid()` for the action's `$key` parameter if needed

  **Must NOT do**:
  - Do NOT call actual API endpoints — use Queue::fake() + mocks
  - Do NOT test `ImportWhatsAppTemplatesJob::handle()` logic (already tested separately)
  - Do NOT test the embedded signup callback flow

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Single feature test class with mock assertions
  - **Skills**: [`phpunit-best-practices`, `laravel-specialist`]
    - `phpunit-best-practices`: Queue::fake() assertions, RefreshDatabase, factory patterns
    - `laravel-specialist`: Filament action test patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with T6)
  - **Blocks**: None
  - **Blocked By**: T3 (needs the dispatch call in ConnectExistingWabaAction)

  **References**:
  - `app/Filament/Admin/WhatsAppSetup/Actions/ConnectExistingWabaAction.php` — The action being tested (with T3 changes)
  - `app/Jobs/ImportWhatsAppTemplatesJob.php` — The job being dispatched
  - `tests/Feature/ImportTenantWhatsAppTemplatesTest.php:87-97` — Queue::fake() + assertPushed example
  - `app/Filament/Admin/WhatsAppSetup/Actions/ConnectExistingWabaAction.php` — Meta\WhatsAppProvider mocks pattern

  **Acceptance Criteria**:
  - [ ] Test `test_dispatches_import_on_successful_manual_connect()` — asserts job is pushed with correct gym ID
  - [ ] Test `test_does_not_dispatch_import_on_failed_manual_connect()` — asserts job is NOT pushed
  - [ ] Test uses `Queue::fake()` and `Queue::assertPushed()` with callback closure
  - [ ] `php artisan test --compact --filter=ConnectExistingWabaActionDispatchesImport` all pass

  **QA Scenarios**:
  ```
  Scenario: Feature tests pass for ConnectExistingWabaAction dispatch
    Tool: Bash (php artisan)
    Preconditions: T1 and T3 completed
    Steps:
      1. Run: php artisan test --compact --filter=ConnectExistingWabaActionDispatchesImport
      2. Check exit code is 0
    Expected Result: All tests pass
    Evidence: .sisyphus/evidence/task-5-test-results.txt

  Scenario: Job NOT dispatched on failed connection
    Tool: Bash (grep test file assertions)
    Preconditions: Test file created
    Steps:
      1. Verify test file has assertion: Queue::assertNotPushed(ImportWhatsAppTemplatesJob::class)
         or equivalent assertion for negative case
    Expected Result: Negative case assertion exists in test
    Evidence: .sisyphus/evidence/task-5-negative-assertion.txt
  ```

  **Evidence to Capture**:
  - [ ] Test file content
  - [ ] Test run output showing pass

  **Commit**: YES (groups with T6)
  - Message: `test(whatsapp): add feature tests for template import dispatch on manual connect`
  - Files: `tests/Feature/Filament/WhatsApp/ConnectExistingWabaActionDispatchesImportTest.php`
  - Pre-commit: `php artisan test --compact --filter=ConnectExistingWabaActionDispatchesImport`

- [ ] 6. Feature test for `EmbeddedSignupController::manualConnect()` dispatch

  **What to do**:
  - Create `tests/Feature/Http/WhatsApp/EmbeddedSignupManualConnectDispatchesImportTest.php`
  - Test that when `EmbeddedSignupController::manualConnect()` succeeds, `ImportWhatsAppTemplatesJob` is dispatched with the correct gym ID
  - Use `Queue::fake()` to intercept the job dispatch
  - Mock the `MetaWhatsAppProvider::connectManualWaba()` to return `['success' => true]`
  - Use `POST` to the route: `route('whatsapp.embedded-signup.manual-connect', ['tenant' => $gym->id])`
  - Follow existing feature test patterns (use `RefreshDatabase`, `actingAs()` for auth, Gym factory)
  - Assert: `Queue::assertPushed(ImportWhatsAppTemplatesJob::class, fn ($job) => $job->gymId === $gym->id)`
  - Also test the negative case: when `connectManualWaba()` returns `['success' => false]`, the job is NOT dispatched
  - Check that the response is a redirect (302) on both success and failure

  **Must NOT do**:
  - Do NOT hit actual API endpoints — use Queue::fake() + mock
  - Do NOT test Twilio paths
  - Do NOT test the embedded signup GET callback path

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Single feature test class with HTTP + queue assertions
  - **Skills**: [`phpunit-best-practices`, `laravel-specialist`]
    - `phpunit-best-practices`: HTTP test methods, Queue assertions, RefreshDatabase
    - `laravel-specialist`: Controller test patterns, route mocking

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with T5)
  - **Blocks**: None
  - **Blocked By**: T4 (needs the dispatch call in EmbeddedSignupController)

  **References**:
  - `app/Http/Controllers/WhatsApp/EmbeddedSignupController.php` — The controller being tested (with T4 changes)
  - `app/Jobs/ImportWhatsAppTemplatesJob.php` — The job being dispatched
  - `routes/web.php:181` — Route definition: `POST /whatsapp/embedded-signup/manual-connect/{tenant}`
  - `tests/Feature/ImportTenantWhatsAppTemplatesTest.php:87-97` — Queue::fake() + assertPushed example

  **Acceptance Criteria**:
  - [ ] Test `test_dispatches_import_on_successful_manual_connect()` — POST to route, assert 302 + job pushed
  - [ ] Test `test_does_not_dispatch_import_on_failed_manual_connect()` — mock failure, assert job NOT pushed
  - [ ] Test uses `Queue::fake()` and `Queue::assertPushed()` with callback
  - [ ] `php artisan test --compact --filter=EmbeddedSignupManualConnectDispatchesImport` all pass

  **QA Scenarios**:
  ```
  Scenario: Feature tests pass for EmbeddedSignupController dispatch
    Tool: Bash (php artisan)
    Preconditions: T1 and T4 completed
    Steps:
      1. Run: php artisan test --compact --filter=EmbeddedSignupManualConnectDispatchesImport
      2. Check exit code is 0
    Expected Result: All tests pass
    Evidence: .sisyphus/evidence/task-6-test-results.txt

  Scenario: Negative case tested
    Tool: Bash (grep test file)
    Steps:
      1. Verify test file has negative case for failed connection
    Expected Result: Negative case test exists
    Evidence: .sisyphus/evidence/task-6-negative-assertion.txt
  ```

  **Evidence to Capture**:
  - [ ] Test file content
  - [ ] Test run output showing pass

  **Commit**: YES (groups with T5)
  - Message: `test(whatsapp): add feature tests for template import dispatch on manual connect`
  - Files: `tests/Feature/Http/WhatsApp/EmbeddedSignupManualConnectDispatchesImportTest.php`
  - Pre-commit: `php artisan test --compact --filter=EmbeddedSignupManualConnectDispatchesImport`

---

## Final Verification Wave

> 4 review agents run in PARALLEL. ALL must APPROVE. Present consolidated results to user and get explicit "okay" before completing.

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read file, run command). For each "Must NOT Have": search codebase for forbidden patterns — reject with file:line if found. Check evidence files exist in `.sisyphus/evidence/`. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `php -l` on all changed files. Run `vendor/bin/pint --dirty --format agent`. Check for: dead code, excessive comments, over-abstraction, unused imports, AI slop patterns.
  Output: `Lint [PASS/FAIL] | Pint [PASS/FAIL] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Start from clean state. Execute EVERY QA scenario from EVERY task — follow exact steps, capture evidence. Test cross-task integration (dispatch triggers correctly). Save to `.sisyphus/evidence/final-qa/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff (git log/diff). Verify 1:1 — everything in spec was built (no missing), nothing beyond spec was built (no creep). Check "Must NOT do" compliance. Detect cross-task contamination.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Commit Strategy

- **T1+T2**: `feat(whatsapp): add dispatchForGym static method to ImportWhatsAppTemplatesJob` — `app/Jobs/ImportWhatsAppTemplatesJob.php`, `tests/Unit/Jobs/ImportWhatsAppTemplatesJobTest.php`
- **T3**: `feat(whatsapp): dispatch template import after manual WABA connect action` — `app/Filament/Admin/WhatsAppSetup/Actions/ConnectExistingWabaAction.php`
- **T4**: `feat(whatsapp): dispatch template import after manual connect controller` — `app/Http/Controllers/WhatsApp/EmbeddedSignupController.php`
- **T5+T6**: `test(whatsapp): add feature tests for template import dispatch on manual connect` — `tests/Feature/WhatsApp/ManualConnectDispatchesImportTest.php`

---

## Success Criteria

### Verification Commands
```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=ImportWhatsAppTemplates
php artisan test --compact --filter=ManualConnectDispatchesImport
```

### Final Checklist
- [ ] `dispatchForGym()` static method exists on `ImportWhatsAppTemplatesJob`
- [ ] `ConnectExistingWabaAction` dispatches job on successful manual connect
- [ ] `EmbeddedSignupController::manualConnect()` dispatches job on successful manual connect
- [ ] All tests pass
- [ ] Pint formatting passes
- [ ] No changes to embedded signup or Twilio flows
