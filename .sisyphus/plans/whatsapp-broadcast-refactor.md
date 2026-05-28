# Plan: WhatsApp Broadcast Refactor

## TL;DR

> **Quick Summary**: Refactor `app/Filament/Admin/Pages/WhatsApp/Broadcast.php` (699 lines) to remove all uses of the deprecated `TwilioWhatsAppService`, extract orchestration into `BroadcastOrchestrator` and rate limiting into `BroadcastRateLimiter`, and use `WhatsAppProviderFactory` (which already exists) for provider resolution.
>
> **Deliverables**:
> - `app/Services/WhatsApp/BroadcastRateLimiter.php` — Atomic rate limiting with `Cache::lock()`
> - `app/Services/WhatsApp/BroadcastOrchestrator.php` — Broadcast creation + validation + dispatch
> - Slimmed `Broadcast.php` — uses orchestrator, no deprecated service references
> - Fixed `sendTestMessage()` — uses `TestMessagingInterface` via provider factory
> - Updated + new PHPUnit tests for rate limiter, orchestrator, and page
>
> **Estimated Effort**: Medium
> **Parallel Execution**: YES — 2 waves
> **Critical Path**: Task 1 → Task 3 → Task 7 (tests)

---

## Context

### Original Request
Refactor the WhatsApp Broadcast Filament page to use the provider interface pattern instead of the deprecated `TwilioWhatsAppService` directly, and extract rate limiting with `Cache::lock()`.

### Interview Summary

**Key Decisions**:
- **Tests After**: Refactor first, then write tests (not TDD)
- **Extract BroadcastOrchestrator**: Business logic goes into injectable service, page becomes thin
- **Extract BroadcastRateLimiter**: Rate limiting (2/day, 1hr cooldown) extracted with `Cache::lock()` for atomicity

**Research Findings**:
- `WhatsAppProviderFactory` already exists at `app/Providers/WhatsAppProviderFactory.php` with `Cache::flexible()` caching — no changes needed
- `SendBroadcastJob` already uses `WhatsAppProviderFactory::create()` correctly — no changes needed
- `WhatsAppBroadcastService` (shared) already provider-agnostic — no changes needed
- `WhatsAppCreditService` already implements `CreditManagementInterface` — no changes needed
- Provider selection: `WhatsAppAccount.provider_type` per-tenant > `config('services.whatsapp.default_provider')` > `'waba'`
- `WhatsAppBroadcast` model has all needed columns (`sent_count`, `failed_count`, `credits_used`, `status`) — no schema changes needed
- Existing tests: `WhatsAppBroadcastValidationTest` (3 tests), `WhatsAppSandboxTestMessageTest` (5 tests) — both use `Livewire::test(Broadcast::class)` pattern
- The `sendTestMessage()` currently instantiates `new TwilioWhatsAppService(...)` — must switch to `TestMessagingInterface::sendTestWhatsAppMessage()`

---

## Work Objectives

### Core Objective
Remove all direct coupling to deprecated `TwilioWhatsAppService` in `Broadcast.php` and introduce clean service extraction pattern.

### Concrete Deliverables
- `app/Services/WhatsApp/BroadcastRateLimiter.php`
- `app/Services/WhatsApp/BroadcastOrchestrator.php`
- Updated `app/Filament/Admin/Pages/WhatsApp/Broadcast.php`
- Updated `tests/Feature/WhatsAppBroadcastValidationTest.php`
- Updated `tests/Feature/WhatsAppSandboxTestMessageTest.php`
- New `tests/Feature/WhatsAppBroadcastRateLimiterTest.php`
- New `tests/Feature/WhatsAppBroadcastOrchestratorTest.php`

### Definition of Done
- [ ] `Broadcast.php` no longer imports or uses `TwilioWhatsAppService` anywhere
- [ ] `BroadcastRateLimiter` uses `Cache::lock()` for atomic checks
- [ ] `BroadcastOrchestrator` handles: validation → rate check → credit check → create broadcast → populate recipients → dispatch job
- [ ] `sendTestMessage()` uses `WhatsAppProviderFactory::create()` → `TestMessagingInterface::sendTestWhatsAppMessage()`
- [ ] All existing WhatsApp tests pass after refactor
- [ ] New tests cover rate limiter and orchestrator
- [ ] `php artisan test --compact --filter=WhatsApp` → all pass

### Must Have
- Remove all references to `App\Services\TwilioWhatsAppService` from `Broadcast.php`
- Rate limiting atomicity via `Cache::lock()`
- Provider-agnostic broadcast creation via factory

### Must NOT Have (Guardrails)
- Do NOT modify `WhatsAppProviderFactory`, `SendBroadcastJob`, `WhatsAppBroadcastService`, or `WhatsAppCreditService`
- Do NOT modify any database schema or migrations
- Do NOT change the Blade view file
- Do NOT modify provider implementations (`MetaWhatsAppProvider`, `TwilioWhatsAppProvider`) internals
- Do NOT change existing form field structure or user-facing behavior

---

## Verification Strategy (MANDATORY)

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit, RefreshDatabase trait)
- **Automated tests**: YES (Tests After)
- **Framework**: PHPUnit 11
- **Pattern**: `Livewire::test(Broadcast::class)` for page tests, standard feature tests for services

### QA Policy
Every task includes agent-executed QA scenarios verified via `php artisan test --compact --filter=<pattern>`.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation — parallel):
├── Task 1: Create BroadcastRateLimiter service [quick]
└── Task 2: Create BroadcastOrchestrator service [unspecified-high]

Wave 2 (Refactor + Tests — parallel, after Wave 1):
├── Task 3: Refactor Broadcast.php to use Orchestrator [deep]
├── Task 4: Fix sendTestMessage() to use factory [quick]
├── Task 5: Write BroadcastRateLimiter tests [phpunit-best-practices]
├── Task 6: Write BroadcastOrchestrator tests [phpunit-best-practices]
└── Task 7: Update existing tests + run full test suite [phpunit-best-practices]

Wave FINAL (Verification — 4 parallel reviews, then user okay):
├── F1: Plan compliance audit (oracle)
├── F2: Code quality review (unspecified-high)
├── F3: Real manual QA (unspecified-high)
└── F4: Scope fidelity check (deep)
→ Present results → Get explicit user okay

Critical Path: Task 1 → Task 3 → Task 7 → F1-F4 → user okay
Parallel Speedup: ~60% faster than sequential
Max Concurrent: 4 (Wave 2)
```

### Dependency Matrix (abbreviated)
- **1-2**: None (start immediately) — 3, 4, 5, 6, 7
- **3**: 1, 2 — 7
- **4**: 1, 2 — 7
- **5**: 1 — 7
- **6**: 2 — 7
- **7**: 3, 4, 5, 6 — F1-F4
- **F1-F4**: 7 — user okay
- **User okay**: F1-F4 — done

---

## TODOs

- [ ] 1. Create `BroadcastRateLimiter` service with `Cache::lock()`

  **What to do**:
  - Create `app/Services/WhatsApp/BroadcastRateLimiter.php`
  - Implement atomic rate limiting using `Cache::lock()` for the 2-broadcasts-per-day and 1-hour-cooldown rules
  - The service must be constructor-injectable (no static calls) and accept `int $gymId`
  - Methods:
    - `getQuota(): array` — returns `['used' => int, 'remaining' => int, 'limit' => int]` based on `WhatsAppBroadcast::whereDate('created_at', today())->count()` vs `config('app.whatsapp_broadcast_limit_per_day')`
    - `canSend(): bool` — checks both daily limit (remaining > 0) AND cooldown (last broadcast > 1hr ago) using `Cache::lock()`
    - `getCooldown(): ?int` — returns minutes remaining until next allowed broadcast, or null
    - `acquireLock(): bool` — attempts to acquire a `Cache::lock("broadcast-rate-{$gymId}")` for 5 seconds, returns success
  - `canSend()` should: (1) acquire lock, (2) check daily count from DB, (3) check last broadcast timestamp, (4) return result and release lock
  - Cooldown comes from `WhatsAppBroadcast::where('gym_id', $gymId)->latest()->first()` and checking `created_at > now()->subHour()`
  - Config keys: `config('app.whatsapp_broadcast_limit_per_day')` for limit (default: 2)
  - All exceptions should bubble up cleanly

  **Must NOT do**:
  - Do NOT modify any model, config, or other service files
  - Do NOT add static methods — use proper DI

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Service extraction with concurrency-sensitive Cache::lock() requires careful implementation
  - **Skills**: [`laravel-best-practices`, `laravel-specialist`]
    - `laravel-best-practices`: Cache::lock() patterns, service architecture
    - `laravel-specialist`: Laravel Cache facade, atomic lock operations
  - **Skills Evaluated but Omitted**:
    - `laravel-security`: No auth/security concerns in rate limiter

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Task 2)
  - **Blocks**: Tasks 3, 4, 5
  - **Blocked By**: None (can start immediately)

  **References**:
  - `app/Filament/Admin/Pages/WhatsApp/Broadcast.php:544-562` — Current rate limiting logic to extract
  - `app/Models/WhatsAppBroadcast.php` — Model for querying broadcast counts
  - `config/app.php` — `whatsapp_broadcast_limit_per_day` config
  - Laravel Cache::lock() docs — atomic lock pattern

  **Acceptance Criteria**:

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Rate limiter allows first broadcast of the day
    Tool: Bash (php artisan tinker)
    Preconditions: No broadcasts exist today for gym_id=1
    Steps:
      1. Instantiate `$limiter = new BroadcastRateLimiter(1)`
      2. Call `$quota = $limiter->getQuota()`
    Expected Result: `$quota['remaining'] === 2` and `$quota['used'] === 0`
    Evidence: task-1-quota-fresh.txt

  Scenario: Rate limiter blocks 3rd broadcast in a day
    Tool: Bash (php artisan tinker)
    Preconditions: 2 broadcasts created today for gym_id=1 via WhatsAppBroadcast::factory()
    Steps:
      1. Instantiate `$limiter = new BroadcastRateLimiter(1)`
      2. Call `$canSend = $limiter->canSend()`
    Expected Result: `$canSend === false`
    Evidence: task-1-limit-reached.txt

  Scenario: Rate limiter detects cooldown after recent broadcast
    Tool: Bash (php artisan tinker)
    Preconditions: 1 broadcast created 30 minutes ago for gym_id=1
    Steps:
      1. Instantiate `$limiter = new BroadcastRateLimiter(1)`
      2. Call `$cooldown = $limiter->getCooldown()`
    Expected Result: `$cooldown` is approx 30
    Evidence: task-1-cooldown.txt
  ```

  **Evidence to Capture:**
  - [ ] task-1-quota-fresh.txt
  - [ ] task-1-limit-reached.txt
  - [ ] task-1-cooldown.txt

  **Commit**: NO

- [ ] 2. Create `BroadcastOrchestrator` service

  **What to do**:
  - Create `app/Services/WhatsApp/BroadcastOrchestrator.php`
  - Extract all broadcast creation logic from `Broadcast.php:sendBroadcast()` into this service
  - The orchestrator must be constructor-injectable with:
    - `BroadcastRateLimiter` (for rate checks)
    - `WhatsAppVariableResolver` (for template variable preview)
  - Single public entry point: `createAndDispatch(array $data, int $gymId, array $selectedMemberIds = []): WhatsAppBroadcast`
  - `$data` structure expected: `['template_id', 'variables', 'recipient_type', 'schedule_type', 'scheduled_at', 'filters']`
  - Logic flow within `createAndDispatch()`:
    1. Validate template exists (`WhatsAppTemplate::findOrFail($templateId)`)
    2. Check `target_count > 0` (derived from member count)
    3. Check rate limit via `$this->rateLimiter->canSend()`
    4. Check credits via `WhatsAppMessageCredit::canSendMessage($targetCount)`
    5. `DB::beginTransaction()`
    6. Create `WhatsAppBroadcast` record with all fields from current page logic
    7. Query members based on `recipient_type` ('all' vs 'filtered') with same filtering logic from current `Broadcast.php:590-612`
    8. Bulk insert recipients via `WhatsAppBroadcastRecipient::insert()` in chunks of 500
    9. `DB::commit()`
    10. If `schedule_type === 'now'`, dispatch `SendBroadcastJob::dispatch($broadcast->id, $gymId)`
    11. Return the broadcast model
  - On validation failure: throw `\Exception` with descriptive message (caught by page)
  - On DB failure: catch, `DB::rollBack()`, re-throw
  - Member query filtering logic should match current `Broadcast.php:getFilteredMembers()` (membership_type, plans, trainers, payment_status scopes)
  - Do NOT include form state, notifications, or Filament-specific logic — that stays in the page

  **Must NOT do**:
  - Do NOT import any Filament classes
  - Do NOT handle notifications — throw exceptions for the page to catch
  - Do NOT touch `WhatsAppProviderFactory`, `SendBroadcastJob`, or `WhatsAppBroadcastService`

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Complex service extraction with DB transactions, multiple dependencies, and validation logic
  - **Skills**: [`laravel-best-practices`, `laravel-specialist`, `laravel-security`]
    - `laravel-best-practices`: Service layer pattern, DI, DB transactions
    - `laravel-specialist`: Eloquent bulk inserts, chunking, job dispatch
    - `laravel-security`: Input validation, credit check integrity

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Task 1)
  - **Blocks**: Tasks 3, 4, 6
  - **Blocked By**: Task 1 (depends on BroadcastRateLimiter)

  **References**:
  - `app/Filament/Admin/Pages/WhatsApp/Broadcast.php:524-640` — Full broadcast logic to extract
  - `app/Filament/Admin/Pages/WhatsApp/Broadcast.php:127-161` — Member filtering logic to reuse
  - `app/Models/WhatsAppBroadcast.php` — Model for broadcast creation
  - `app/Models/WhatsAppBroadcastRecipient.php` — Model for recipient creation
  - `app/Models/WhatsAppMessageCredit.php:canSendMessage()` — Credit check method
  - `app/Jobs/SendBroadcastJob.php` — Job to dispatch (already uses factory)
  - `app/Services/WhatsApp/BroadcastRateLimiter.php` — This task's output (from Task 1)

  **Acceptance Criteria**:
  - [ ] Service can be instantiated via constructor injection with `BroadcastRateLimiter` + `WhatsAppVariableResolver`
  - [ ] `createAndDispatch()` validates all preconditions and throws exceptions for each failure case
  - [ ] Recipients are inserted via bulk chunking (same 500-chunk pattern as current code)
  - [ ] `SendBroadcastJob` is dispatched for immediate broadcasts
  - [ ] No Filament-specific code in the service

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Orchestrator creates broadcast and dispatches job
    Tool: Bash (php artisan tinker)
    Preconditions: Gym with 5 members, approved template, sufficient credits, no broadcasts today
    Steps:
      1. Create BroadcastRateLimiter and Orchestrator
      2. Call createAndDispatch() with valid data
    Expected Result: WhatsAppBroadcast record created, recipients inserted, SendBroadcastJob dispatched
    Evidence: task-2-broadcast-created.txt

  Scenario: Orchestrator throws on insufficient credits
    Tool: Bash (php artisan tinker)
    Preconditions: Gym with 5 members but credits exhausted
    Steps:
      1. Create Orchestrator
      2. Call createAndDispatch() with valid data
    Expected Result: \Exception thrown with "Insufficient credits" message
    Evidence: task-2-insufficient-credits.txt
  ```

  **Evidence to Capture:**
  - [ ] task-2-broadcast-created.txt
  - [ ] task-2-insufficient-credits.txt

  **Commit**: NO

- [ ] 3. Refactor `Broadcast.php` to use `BroadcastOrchestrator` and remove deprecated service

  **What to do**:
  - Remove line 13: `use App\Services\TwilioWhatsAppService;` (deprecated service import)
  - Add `use App\Services\WhatsApp\BroadcastOrchestrator;` import
  - Add `protected BroadcastOrchestrator $broadcastOrchestrator;` as a property (resolve via `app()` in `mount()` or via `__construct()`)
  - In `mount()`, resolve: `$this->broadcastOrchestrator = app(BroadcastOrchestrator::class);`
  - Replace entire `sendBroadcast()` method body (lines 524-640) with:
    ```php
    private function sendBroadcast(): void
    {
        try {
            // Validate form
            $this->form->validate();

            // Prepare data for orchestrator
            $data = [
                'template_id' => $this->data['template_id'],
                'variables' => $this->data['variables'] ?? [],
                'recipient_type' => $this->data['recipient_type'] ?? 'all',
                'schedule_type' => $this->data['schedule_type'] ?? 'now',
                'scheduled_at' => $this->data['scheduled_at'] ?? null,
                'filters' => $this->data['filters'] ?? [],
            ];

            $scheduleType = $this->data['schedule_type'] ?? 'now';

            $broadcast = $this->broadcastOrchestrator->createAndDispatch(
                $data,
                $this->getGymId(),
                $this->selectedMemberIds,
            );

            Notification::make()
                ->title($scheduleType === 'now' ? 'Broadcast Started!' : 'Broadcast Scheduled!')
                ->body($scheduleType === 'now'
                    ? 'Sending messages to ' . number_format($broadcast->target_count) . ' members.'
                    : 'Scheduled for ' . ($broadcast->scheduled_at?->format('M d, Y g:i A') ?? ''))
                ->success()
                ->send();

            $this->initializeForm();
            $this->loadTemplates();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Broadcast Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    ```
  - Replace the quota display in the form (lines 220-255) to use an inline anonymous function or keep as-is but refer to `WhatsAppBroadcast` directly (it's display-only, not logic)
  - Remove the `processBroadcast()` helper method (lines 679-682) — it's now handled by orchestration
  - Keep `getFilteredMembers()`, `getAllMemberCount()`, `getFilteredMemberCount()` as-is — orchestrator needs them internally or duplicate the query logic
  - Keep all form schema, table, filter methods, template preview, and UI state as-is
  - Remove the `$creditBalance` block that was inline in `sendBroadcast()` — orchestrator handles it
  - Clean up unused imports: `WhatsAppMessageCredit`, `DB`, `WhatsAppBroadcastRecipient` may become unused if they were only used in `sendBroadcast()`
  - But `WhatsAppBroadcast` is still used in the quota display (line 226-232), so keep the import
  - The `Trainer`, `MembershipPlan`, `GymMember` imports are still used in filters/member queries

  **Must NOT do**:
  - Do NOT change any form schemas, table definitions, or UI behavior
  - Do NOT change the Blade view file
  - Do NOT remove imports that are still used elsewhere in the file

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Multi-concern refactoring — needs to understand all current usages and ensure nothing is broken
  - **Skills**: [`laravel-best-practices`, `laravel-specialist`]
    - `laravel-best-practices`: Clean page architecture, service injection
    - `laravel-specialist`: Filament page patterns, Livewire integration

  **Parallelization**:
  - **Can Run In Parallel**: NO (must be sequential within this task)
  - **Parallel Group**: Wave 2 (with Tasks 4, 5, 6, 7)
  - **Blocks**: Task 7
  - **Blocked By**: Tasks 1, 2

  **References**:
  - `app/Filament/Admin/Pages/WhatsApp/Broadcast.php` — The entire file to refactor
  - `app/Services/WhatsApp/BroadcastOrchestrator.php` — This task's dependency (from Task 2)
  - `app/Services/WhatsApp/BroadcastRateLimiter.php` — This task's dependency (from Task 1)

  **Acceptance Criteria**:
  - [ ] No `TwilioWhatsAppService` import or usage remains
  - [ ] `sendBroadcast()` calls `BroadcastOrchestrator::createAndDispatch()` instead of inline logic
  - [ ] All existing form, table, filter, and preview behavior is preserved
  - [ ] `php artisan test --compact --filter=WhatsAppBroadcastValidation` passes

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Broadcast page loads with form and quota
    Tool: Bash (php artisan test)
    Preconditions: Gym exists with template, credits, members
    Steps:
      1. Run: php artisan test --compact --filter=WhatsAppBroadcastValidation::test_broadcast_sends_successfully_with_valid_data
    Expected Result: TEST PASS
    Evidence: task-3-page-load.txt

  Scenario: Broadcast page shows error when no template selected
    Tool: Bash (php artisan test)
    Preconditions: Same test setup
    Steps:
      1. Run: php artisan test --compact --filter=WhatsAppBroadcastValidation::test_template_id_is_required
    Expected Result: TEST PASS
    Evidence: task-3-no-template.txt
  ```

  **Evidence to Capture:**
  - [ ] task-3-page-load.txt
  - [ ] task-3-no-template.txt

  **Commit**: NO

- [ ] 4. Fix `sendTestMessage()` to use `WhatsAppProviderFactory` instead of deprecated service

  **What to do**:
  - In `Broadcast.php`, replace the `sendTestMessage()` method body (lines 642-677)
  - Remove `new TwilioWhatsAppService(...)` instantiation (line 658-659)
  - Add `use App\Providers\WhatsAppProviderFactory;` import (if not already present)
  - New implementation:
    ```php
    private function sendTestMessage(): void
    {
        try {
            if (! config('services.twilio.use_sandbox', false)) {
                throw new \Exception('Sandbox mode is not enabled...');
            }

            $testPhoneNumber = config('services.twilio.sandbox_recipient_number');
            $testMessage = 'This is a test message...';

            if (! $testPhoneNumber) {
                throw new \Exception('No test phone number configured...');
            }

            $provider = WhatsAppProviderFactory::create((int) $this->getGymId());

            // Use TestMessagingInterface
            $result = $provider->sendTestWhatsAppMessage(
                (int) $this->getGymId(),
                $testPhoneNumber
            );

            if ($result['success']) {
                Notification::make()
                    ->title('Test Message Sent!')
                    ->body('Test message sent successfully to ' . $testPhoneNumber)
                    ->success()
                    ->send();
            } else {
                throw new \Exception($result['error'] ?? 'Failed to send test message');
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Test Message Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    ```
  - Both `MetaWhatsAppProvider` and `TwilioWhatsAppProvider` implement `TestMessagingInterface::sendTestWhatsAppMessage()` — this is correct
  - Verify the import: `use App\Interfaces\TestMessagingInterface;` is NOT needed — we're calling through the provider interface, not the interface directly

  **Must NOT do**:
  - Do NOT remove the sandbox config check — it must remain as-is
  - Do NOT change the notification messages

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Focused single-method replacement, well-understood pattern
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Provider factory usage, interface delegation

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 3, 5, 6, 7)
  - **Blocks**: Task 7
  - **Blocked By**: Tasks 1, 2

  **References**:
  - `app/Filament/Admin/Pages/WhatsApp/Broadcast.php:642-677` — Current sendTestMessage to replace
  - `app/Providers/WhatsAppProviderFactory.php` — Factory to use (already imported in SendBroadcastJob)
  - `app/Interfaces/TestMessagingInterface.php` — The interface method to call
  - `app/Providers/TwilioWhatsAppProvider.php:119` — sendTestWhatsAppMessage implementation
  - `app/Providers/MetaWhatsAppProvider.php:119` — sendTestWhatsAppMessage implementation

  **Acceptance Criteria**:
  - [ ] No `new TwilioWhatsAppService(...)` instantiation remains
  - [ ] `sendTestMessage()` calls `WhatsAppProviderFactory::create()->sendTestWhatsAppMessage()`
  - [ ] All existing sandbox tests pass

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Test message uses provider factory instead of deprecated service
    Tool: Bash (php artisan test)
    Preconditions: Sandbox enabled, template exists
    Steps:
      1. Run: php artisan test --compact --filter=WhatsAppSandboxTestMessageTest
    Expected Result: All tests pass (they use sendTestMessage internally)
    Evidence: task-4-test-message.txt

  Scenario: Test message fails correctly with sandbox disabled
    Tool: Bash (php artisan test)
    Preconditions: sandbox disabled
    Steps:
      1. Run: php artisan test --compact --filter=WhatsAppSandboxTestMessageTest::test_send_test_message_fails_when_sandbox_is_not_enabled
    Expected Result: TEST PASS
    Evidence: task-4-sandbox-disabled.txt
  ```

  **Evidence to Capture:**
  - [ ] task-4-test-message.txt
  - [ ] task-4-sandbox-disabled.txt

  **Commit**: NO

- [ ] 5. Write PHPUnit tests for `BroadcastRateLimiter`

  **What to do**:
  - Create `tests/Feature/WhatsAppBroadcastRateLimiterTest.php`
  - Use `RefreshDatabase` trait
  - Follow existing test patterns from `WhatsAppBroadcastValidationTest.php`
  - Tests needed:
    1. `test_quota_returns_two_remaining_when_no_broadcasts_today()` — verify fresh quota
    2. `test_quota_returns_zero_remaining_when_limit_reached()` — create 2 broadcasts, verify remaining=0
    3. `test_can_send_returns_true_when_under_limit()` — no broadcasts, `canSend()` returns true
    4. `test_can_send_returns_false_when_limit_reached()` — 2 broadcasts, `canSend()` returns false
    5. `test_can_send_returns_false_during_cooldown()` — 1 broadcast 30min ago, verify cooldown active
    6. `test_quota_resets_next_day()` — create broadcasts today, verify count resets tomorrow
    7. `test_acquire_lock_returns_true_when_not_held()` — lock acquisition test
  - Mock `now()` where needed using `Carbon::setTestNow()`
  - Each test should instantiate `BroadcastRateLimiter` with gym ID directly

  **Must NOT do**:
  - Do NOT test the Broadcast page — test only the RateLimiter service
  - Do NOT test `Cache::lock()` internals — test observable behavior

  **Recommended Agent Profile**:
  - **Category**: `phpunit-best-practices`
    - Reason: Writing new test files, following existing patterns
  - **Skills**: [`phpunit-best-practices`]
    - `phpunit-best-practices`: Test structure, factories, assertions

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 3, 4, 6, 7)
  - **Blocks**: Task 7
  - **Blocked By**: Task 1

  **References**:
  - `tests/Feature/WhatsAppBroadcastValidationTest.php` — Test pattern to follow
  - `app/Services/WhatsApp/BroadcastRateLimiter.php` — This task's dependency (from Task 1)
  - `app/Models/WhatsAppBroadcast.php` — Factory for creating test broadcasts

  **Acceptance Criteria**:
  - [ ] All 7 test methods cover quota, canSend, cooldown, reset, and lock scenarios
  - [ ] `php artisan test --compact --filter=WhatsAppBroadcastRateLimiter` → all pass

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: All rate limiter tests pass
    Tool: Bash
    Preconditions: Clean database
    Steps:
      1. php artisan test --compact --filter=WhatsAppBroadcastRateLimiter
    Expected Result: All X tests pass, 0 failures
    Evidence: task-5-rate-limiter-tests.txt
  ```

  **Evidence to Capture:**
  - [ ] task-5-rate-limiter-tests.txt

  **Commit**: NO

- [ ] 6. Write PHPUnit tests for `BroadcastOrchestrator`

  **What to do**:
  - Create `tests/Feature/WhatsAppBroadcastOrchestratorTest.php`
  - Use `RefreshDatabase` trait
  - Tests needed:
    1. `test_creates_broadcast_and_recipients_successfully()` — full happy path
    2. `test_throws_exception_when_template_not_found()` — invalid template_id
    3. `test_throws_exception_when_no_recipients()` — targetCount = 0
    4. `test_throws_exception_when_rate_limit_exceeded()` — mock rate limiter to return false
    5. `test_throws_exception_when_insufficient_credits()` — exhaust credits
    6. `test_dispatches_send_broadcast_job_for_immediate_send()` — verify `Queue::fake()` and `SendBroadcastJob` assertion
    7. `test_schedules_broadcast_without_dispatching_job()` — scheduled broadcast does NOT dispatch
    8. `test_filters_members_by_recipient_type()` — filtered vs all recipients
  - Use `Queue::fake()` and `Bus::assertDispatched()` for job assertion tests
  - Instantiate `BroadcastOrchestrator` with a real `BroadcastRateLimiter` (don't mock it — test integration)

  **Must NOT do**:
  - Do NOT test the Broadcast page — test only the Orchestrator service

  **Recommended Agent Profile**:
  - **Category**: `phpunit-best-practices`
    - Reason: New test file, queue assertions, model factories
  - **Skills**: [`phpunit-best-practices`, `laravel-specialist`]
    - `phpunit-best-practices`: Test structure, Queue fake, assertions
    - `laravel-specialist`: Bus::assertDispatched, Queue::fake

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 3, 4, 5, 7)
  - **Blocks**: Task 7
  - **Blocked By**: Task 2

  **References**:
  - `tests/Feature/WhatsAppBroadcastValidationTest.php` — Test pattern to follow
  - `app/Services/WhatsApp/BroadcastOrchestrator.php` — This task's dependency (from Task 2)
  - `app/Jobs/SendBroadcastJob.php` — Job to assert against
  - `app/Models/WhatsAppBroadcast.php` — Factory for verification

  **Acceptance Criteria**:
  - [ ] All 8 test methods cover happy path, errors, job dispatch, and scheduling
  - [ ] `php artisan test --compact --filter=WhatsAppBroadcastOrchestrator` → all pass

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: All orchestrator tests pass
    Tool: Bash
    Preconditions: Clean database
    Steps:
      1. php artisan test --compact --filter=WhatsAppBroadcastOrchestrator
    Expected Result: All X tests pass, 0 failures
    Evidence: task-6-orchestrator-tests.txt
  ```

  **Evidence to Capture:**
  - [ ] task-6-orchestrator-tests.txt

  **Commit**: NO

- [ ] 7. Update existing tests and run full test suite

  **What to do**:
  - Review and update `tests/Feature/WhatsAppBroadcastValidationTest.php`:
    - `test_broadcast_sends_successfully_with_valid_data()` — may need minor adjustments if the data shape changed (e.g., if `sendBroadcast()` now calls `form->validate()` first, the test must fill the form properly)
    - Ensure all 3 existing tests still pass with the refactored page
  - Review and update `tests/Feature/WhatsAppSandboxTestMessageTest.php`:
    - `test_send_test_message_succeeds_with_sandbox_enabled()` — depends on `TwilioWhatsAppService` mock behavior; may need to adjust to work with factory
    - `test_send_test_message_uses_selected_member_phone_when_available()` — same concern
  - The tests call `sendTestMessage()` via `Livewire::test()` — the method signature and behavior should be preserved, so tests should pass with minimal changes
  - Run the full test suite: `php artisan test --compact --filter=WhatsApp`
  - Fix any failing tests
  - The `TwilioWhatsAppService::sendTemplateTestMessage()` mock dependency needs attention:
    - Currently the test in `test_send_test_message_succeeds_with_sandbox_enabled` calls `sendTestMessage()` which internally calls `$provider->sendTestWhatsAppMessage()` — ensure the factory resolves a provider that can handle this
    - If the provider makes an external API call, the test may need `Http::fake()` or a mock of the factory

  **Must NOT do**:
  - Do NOT delete any existing tests
  - Do NOT change test assertions without understanding why

  **Recommended Agent Profile**:
  - **Category**: `phpunit-best-practices`
    - Reason: Update existing tests + run full suite
  - **Skills**: [`phpunit-best-practices`, `laravel-specialist`]
    - `phpunit-best-practices`: Test updating, Http faking
    - `laravel-specialist`: Mocking provider factory in Livewire tests

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on all prior tasks)
  - **Parallel Group**: Wave 2 (final sequential step)
  - **Blocks**: F1-F4
  - **Blocked By**: Tasks 3, 4, 5, 6

  **References**:
  - `tests/Feature/WhatsAppBroadcastValidationTest.php` — Existing tests to update
  - `tests/Feature/WhatsAppSandboxTestMessageTest.php` — Existing tests to update
  - `app/Filament/Admin/Pages/WhatsApp/Broadcast.php` — Refactored page (from Task 3)
  - `app/Services/WhatsApp/BroadcastOrchestrator.php` — Orchestrator (from Task 2)
  - `app/Services/WhatsApp/BroadcastRateLimiter.php` — Rate limiter (from Task 1)

  **Acceptance Criteria**:
  - [ ] `php artisan test --compact --filter=WhatsApp` → ALL tests pass
  - [ ] No existing tests were deleted

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: All WhatsApp tests pass after refactor
    Tool: Bash
    Preconditions: Clean database, all previous tasks completed
    Steps:
      1. php artisan test --compact --filter=WhatsApp
    Expected Result: All tests pass (expected count: ~16 tests)
    Evidence: task-7-all-tests-pass.txt

  Scenario: Individual test files pass
    Tool: Bash
    Steps:
      1. php artisan test --compact --filter=WhatsAppBroadcastValidation
      2. php artisan test --compact --filter=WhatsAppSandbox
      3. php artisan test --compact --filter=WhatsAppBroadcastRateLimiter
      4. php artisan test --compact --filter=WhatsAppBroadcastOrchestrator
    Expected Result: All 4 commands return OK (pass)
    Evidence: task-7-individual-tests.txt
  ```

  **Evidence to Capture:**
  - [ ] task-7-all-tests-pass.txt
  - [ ] task-7-individual-tests.txt

  **Commit**: NO

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read file, check imports). For each "Must NOT Have": search codebase for forbidden patterns — reject with file:line if found. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `vendor/bin/pint --dirty --format agent` + `php artisan test --compact --filter=WhatsApp`. Review changed files for: abandoned dead code, `@ts-ignore` equivalents, commented-out code, unused imports. Check AI slop: over-abstraction, generic names.
  Output: `Build/Pint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Start from clean state (`RefreshDatabase`). Execute EVERY QA scenario from EVERY task — follow exact steps. Test cross-task integration. Test edge cases. Save evidence to `.sisyphus/evidence/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff (`git diff`). Verify 1:1 — everything in spec was built (no missing), nothing beyond spec was built (no creep). Check "Must NOT do" compliance.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | VERDICT`

---

## Commit Strategy

- **1**: `feat(whatsapp): add BroadcastRateLimiter service with Cache::lock()`
- **2**: `feat(whatsapp): add BroadcastOrchestrator service`
- **3**: `refactor(whatsapp): slim Broadcast.php to use BroadcastOrchestrator`
- **4**: `fix(whatsapp): use WhatsAppProviderFactory for sendTestMessage()`
- **5**: `test(whatsapp): add BroadcastRateLimiter tests`
- **6**: `test(whatsapp): add BroadcastOrchestrator tests`
- **7**: `test(whatsapp): update existing tests for refactored code`

---

## Success Criteria

### Verification Commands
```bash
php artisan test --compact --filter=WhatsAppBroadcast  # Expected: all pass
php artisan test --compact --filter=WhatsAppSandbox    # Expected: all pass
php artisan test --compact --filter=WhatsApp           # Expected: all pass
```

### Final Checklist
- [ ] `grep -r "TwilioWhatsAppService" app/Filament/Admin/Pages/WhatsApp/Broadcast.php` → 0 matches
- [ ] `vendor/bin/pint --dirty --format agent` → clean
- [ ] All "Must Have" present
- [ ] All "Must NOT Have" absent
- [ ] All WhatsApp tests pass
