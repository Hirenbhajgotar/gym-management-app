# WABA Connection Integration into WhatsApp Setup Page

## TL;DR

> **Quick Summary**: Integrate WABA (WhatsApp Business Account) connection options directly into the WhatsApp Setup wizard's Step 2, allowing users to either connect an existing WABA (with waba_id, phone number, access token) or create a new one through the system — replacing the need to visit a separate ManualWabaConnection page.
> 
> **Deliverables**:
> - Modified `WhatsAppSetup.php` with dual-path WABA connection in Step 2
> - Updated Blade view with clean UI for the two connection options
> - New `connectExistingWaba()` method ported from ManualWabaConnection logic
> - Updated PHPUnit tests covering both paths
> - Code formatted with Pint
> 
> **Estimated Effort**: Short (2-4 tasks, focused scope)
> **Parallel Execution**: YES - 2 waves
> **Critical Path**: Task 1 → Task 2 → Task 3 → Task 4

---

## Context

### Original Request
User wants to enhance the WhatsApp Setup page to support WABA connection. Users should be able to choose between connecting an existing WABA account (providing waba_id, phone number, access token) or creating a new WABA through the system. The manual WABA connection logic already exists in `ManualWabaConnection.php` and needs to be integrated into `WhatsAppSetup.php`. The `ManualWabaConnection.php` file must NOT be deleted.

### Interview Summary
**Key Discussions**:
- The existing `getWabaSetupStep()` (Step 2 in the wizard) currently only shows "Create WABA" button + embedded signup flow
- `ManualWabaConnection.php` has the form fields and `connectWaba()` method calling `TwilioAccountManager::connectManualWaba()`
- UI must clearly separate both options with a professional, guided interface
- Target audience: gym owners (non-technical users)

**Research Findings**:
- `TwilioAccountManager::connectManualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken)` is already implemented at line 280
- `WhatsAppSetup.php` uses `HasTenantAccess` trait for `getGymId()`, `getGym()`, `getTenantSlug()` — ManualWabaConnection uses `Filament::getTenant()?->id` directly, so the integrated version should use the trait methods
- The Blade view (`whats-app-setup.blade.php`) renders `$this->form` in each viewState — minimal changes needed there
- Existing test file: `tests/Feature/Filament/WhatsAppSetupTest.php`
- The wizard uses `afterValidation` callbacks on each step to advance `$setupStep`

### Gaps Addressed
- **Edge case**: What if user starts "Connect Existing" but wants to switch to "Create New"? → Toggle/radio must allow switching freely before submission
- **Edge case**: What if user already has a WABA connected? → Step 2 already checks `$this->wabaCreated` and disables the button
- **Validation**: Phone number format validation needed (existing ManualWabaConnection uses `->tel()` on TextInput)
- **After connection**: Manual WABA connection should set `$this->wabaCreated = true` and advance to step 3, same as createWaba() does

---

## Work Objectives

### Core Objective
Integrate the manual WABA connection form into the WhatsApp Setup wizard's Step 2, providing a clean dual-path UI (Connect Existing vs Create New) so users never need to visit the separate ManualWabaConnection page.

### Concrete Deliverables
- `app/Filament/Admin/Pages/WhatsAppSetup.php` — modified with new property, form fields, and `connectExistingWaba()` method
- `resources/views/filament/admin/pages/whats-app-setup.blade.php` — no changes expected (form renders via `$this->form`)
- `tests/Feature/Filament/WhatsAppSetupTest.php` — updated with tests for both WABA connection paths

### Definition of Done
- [ ] User can see two clear options in Step 2: "Connect Existing WABA" and "Create New WABA"
- [ ] Selecting "Connect Existing" shows form fields for waba_id, phone_number, meta_access_token
- [ ] Selecting "Create New" shows the existing create WABA button + embedded signup flow
- [ ] Submitting "Connect Existing" successfully calls `TwilioAccountManager::connectManualWaba()` and advances to Step 3
- [ ] All existing tests still pass
- [ ] New tests cover both connection paths
- [ ] Code passes `vendor/bin/pint --dirty`

### Must Have
- Toggle/radio to switch between "Connect Existing" and "Create New" in Step 2
- Form fields: waba_id (required), phone_number (required, tel format), meta_access_token (optional, password field)
- `connectExistingWaba()` method in WhatsAppSetup.php
- Proper success/error notifications matching existing pattern
- Step advancement after successful connection (`$this->wabaCreated = true`, `$this->setupStep = 3`)
- Instructional helper text similar to ManualWabaConnection (how to find WABA details)

### Must NOT Have (Guardrails)
- Do NOT delete or modify `ManualWabaConnection.php` or its blade view
- Do NOT change the wizard step order or numbering
- Do NOT modify `TwilioAccountManager.php` — the `connectManualWaba()` method is already working
- Do NOT add new dependencies or packages
- Do NOT over-engineer — no abstract factories, strategy patterns, or unnecessary abstractions
- Do NOT add inline code comments unless logic is exceptionally complex (PHPDoc blocks only per project rules)
- Do NOT use `env()` directly — use `config()` if configuration is needed
- Do NOT change the existing "Create New WABA" flow behavior

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed. No exceptions.

### Test Decision
- **Infrastructure exists**: YES — PHPUnit with `tests/Feature/Filament/WhatsAppSetupTest.php`
- **Automated tests**: YES (Tests-after) — Add new test methods for manual WABA connection flow
- **Framework**: PHPUnit via `php artisan test`

### QA Policy
Every task MUST include agent-executed QA scenarios.
Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **Backend/Filament**: Use Bash — run PHPUnit tests, check PHP syntax, run Pint
- **UI rendering**: Use Bash — PHP artisan route:list to verify page loads, Livewire test assertions

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Start Immediately — backend logic):
├── Task 1: Add WABA connection toggle + form fields + connectExistingWaba() method [unspecified-high]

Wave 2 (After Wave 1 — testing + formatting):
├── Task 2: Write PHPUnit tests for both WABA connection paths [unspecified-high]
├── Task 3: Run Pint formatter + final validation [quick]

Wave FINAL (After ALL tasks — verification):
├── Task F1: Plan compliance audit [deep]
├── Task F2: Code quality + full test suite [unspecified-high]
```

### Dependency Matrix

| Task | Depends On | Blocks |
|------|-----------|--------|
| 1    | —         | 2, 3   |
| 2    | 1         | F1, F2 |
| 3    | 1         | F1, F2 |
| F1   | 2, 3      | —      |
| F2   | 2, 3      | —      |

### Agent Dispatch Summary

- **Wave 1**: 1 task — T1 → `unspecified-high`
- **Wave 2**: 2 tasks — T2 → `unspecified-high`, T3 → `quick`
- **FINAL**: 2 tasks — F1 → `deep`, F2 → `unspecified-high`

---

## TODOs

- [ ] 1. Integrate WABA Connection Options into WhatsAppSetup Step 2

  **What to do**:
  - Add a new public property `public string $wabaConnectionMethod = 'create_new';` to WhatsAppSetup.php to track which path the user selects
  - Modify `getWabaSetupStep()` to include a toggle/radio between two options:
    - **"Connect Existing WABA"** — shows form fields for manual connection
    - **"Create New WABA"** — shows the existing create WABA button + embedded signup (current behavior)
  - Use `Forms\Components\Radio::make('waba_connection_method')` with two options, using `->live()` to reactively show/hide the relevant form section
  - When "Connect Existing" is selected, show:
    - `Forms\Components\TextInput::make('waba_id')` — required, placeholder `123456789012345`, helperText explaining where to find it
    - `Forms\Components\TextInput::make('phone_number')` — required, `->tel()`, placeholder `+1234567890`
    - `Forms\Components\TextInput::make('meta_access_token')` — optional, `->password()`, `->dehydrated(fn ($state) => filled($state))`
    - A collapsible `Forms\Components\Section` with instructions on how to find WABA details (port from ManualWabaConnection.php lines 101-118)
    - `Forms\Components\Actions\Action::make('connectExistingWaba')` button
  - When "Create New" is selected, show the existing content (createWaba button, embedded signup info, etc.) — this is the current behavior, just wrap it in a `->visible(fn (Get $get) => $get('waba_connection_method') === 'create_new')` condition
  - Add new method `connectExistingWaba()`:
    ```php
    public function connectExistingWaba(): void
    {
        // Validate required fields from form state
        // Call app(TwilioAccountManager::class)->connectManualWaba($gymId, $wabaId, $phoneNumber, $metaAccessToken)
        // On success: set $this->wabaCreated = true, $this->setupStep = 3, refresh account, send success notification
        // On failure: Log error, send danger notification
    }
    ```
  - Use `HasTenantAccess` trait methods (`$this->getGymId()`) — NOT `Filament::getTenant()?->id` directly
  - Initialize `waba_connection_method` in `initializeFormData()` data array
  - If the account already has a WABA (`$this->wabaCreated === true`), both options should be disabled/hidden — show "WABA Created ✓" status instead (existing behavior already handles this via `->disabled($this->wabaCreated)`)
  - Ensure the `afterValidation` callback on Step 2 still works correctly for both paths

  **Must NOT do**:
  - Do NOT modify ManualWabaConnection.php
  - Do NOT change TwilioAccountManager.php
  - Do NOT reorganize the wizard steps
  - Do NOT add excessive inline comments

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Backend PHP with Filament forms — not purely visual, not trivially quick, requires understanding Filament reactive forms
  - **Skills**: [`fluxui-development`]
    - `fluxui-development`: The form uses Filament form components which follow similar patterns to Flux UI — helpful for reactive `->visible()` and `->live()` patterns
  - **Skills Evaluated but Omitted**:
    - `tailwindcss-development`: Not needed — HTML/CSS markup is in HtmlString placeholders, not Tailwind utility classes being added
    - `volt-development`: Not a Volt component — this is a Filament Page class

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 1 (sole task)
  - **Blocks**: Tasks 2, 3
  - **Blocked By**: None (can start immediately)

  **References** (CRITICAL - Be Exhaustive):

  **Pattern References** (existing code to follow):
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:348-393` — Current `getWabaSetupStep()` method — this is the method being modified. Study the existing button structure, `$this->wabaCreated` disable pattern, embedded signup section, and `afterValidation` callback
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:303-346` — `getCreateAccountStep()` method — shows the pattern for form fields inside wizard steps (Grid layout, TextInput, Textarea, afterValidation with try/catch/Halt)
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:645-694` — `createWaba()` method — shows the existing WABA creation flow pattern (try/catch, notification, state updates) — your `connectExistingWaba()` should follow this same pattern
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:164-181` — `initializeFormData()` — add `waba_connection_method` to the `$this->data` array here
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:44-84` — Public properties section — add `$wabaConnectionMethod` property here

  **API/Type References** (contracts to implement against):
  - `app/Services/TwilioAccountManager.php:280-329` — `connectManualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array` — returns `['success' => bool, 'waba_id' => string, 'sender_sid' => string, 'error' => string]`. Call this from `connectExistingWaba()`
  - `app/Traits/HasTenantAccess.php` — Use `$this->getGymId()` (returns `int|string`), `$this->getGym()` (returns `Gym`), `$this->getTenantSlug()` (returns `string`)

  **Source References** (code to port):
  - `app/Filament/Admin/Pages/WhatsApp/ManualWabaConnection.php:73-98` — Form fields to port: `waba_id` TextInput, `phone_number` TextInput with `->tel()`, `meta_access_token` TextInput with `->password()` and `->dehydrated()`, and the connect action button
  - `app/Filament/Admin/Pages/WhatsApp/ManualWabaConnection.php:101-118` — Instructional section "How to Find Your WABA Details" — port this collapsible section with the step-by-step instructions
  - `app/Filament/Admin/Pages/WhatsApp/ManualWabaConnection.php:123-185` — `connectWaba()` method — port this logic into `connectExistingWaba()`, replacing `Filament::getTenant()?->id` with `$this->getGymId()` and ensuring state updates match WhatsAppSetup patterns (`$this->wabaCreated = true`, `$this->setupStep = 3`, notification)

  **External References**:
  - Filament Forms: Reactive fields with `->live()` and `->visible(fn (Get $get) => ...)` — use `search-docs` tool with queries `['reactive forms', 'live', 'visible']`
  - Filament Radio component: `Forms\Components\Radio::make()` with `->options()` — use `search-docs` tool with queries `['radio', 'radio options']`

  **Acceptance Criteria**:

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: PHP syntax validation
    Tool: Bash
    Preconditions: Task 1 changes applied
    Steps:
      1. Run: php -l app/Filament/Admin/Pages/WhatsAppSetup.php
    Expected Result: "No syntax errors detected"
    Failure Indicators: Parse error message
    Evidence: .sisyphus/evidence/task-1-php-syntax.txt

  Scenario: Page loads without error
    Tool: Bash (tinker)
    Preconditions: Task 1 changes applied
    Steps:
      1. Run tinker: app(\App\Filament\Admin\Pages\WhatsAppSetup::class); echo 'OK';
    Expected Result: No exceptions, "OK" printed
    Failure Indicators: Class not found, syntax error, missing import
    Evidence: .sisyphus/evidence/task-1-class-loads.txt

  Scenario: Pint formatting check
    Tool: Bash
    Preconditions: Task 1 changes applied
    Steps:
      1. Run: vendor/bin/pint --test app/Filament/Admin/Pages/WhatsAppSetup.php
    Expected Result: Exit code 0 (or identify needed fixes)
    Failure Indicators: Non-zero exit code with formatting issues
    Evidence: .sisyphus/evidence/task-1-pint-check.txt
  ```

  **Commit**: YES
  - Message: `feat(whatsapp): integrate WABA connection options into setup wizard step 2`
  - Files: `app/Filament/Admin/Pages/WhatsAppSetup.php`
  - Pre-commit: `php -l app/Filament/Admin/Pages/WhatsAppSetup.php`

---

- [ ] 2. Write PHPUnit Tests for WABA Connection Paths

  **What to do**:
  - Open existing test file `tests/Feature/Filament/WhatsAppSetupTest.php` and study existing test patterns
  - Add new test methods:
    - `test_waba_setup_step_shows_connection_method_options()` — Assert that Step 2 renders both "Connect Existing" and "Create New" radio options
    - `test_connect_existing_waba_validates_required_fields()` — Assert that submitting without waba_id/phone_number shows validation errors
    - `test_connect_existing_waba_success()` — Mock `TwilioAccountManager::connectManualWaba()` to return success, call `connectExistingWaba()`, assert `$this->wabaCreated === true` and success notification
    - `test_connect_existing_waba_failure()` — Mock `TwilioAccountManager::connectManualWaba()` to throw exception, assert error notification
    - `test_create_new_waba_still_works()` — Ensure the existing "Create New" path still functions (regression test)
  - Use Livewire testing: `livewire(WhatsAppSetup::class)` with proper tenant setup (follow existing test patterns in the file)
  - Use factories for WhatsAppAccount model if they exist, otherwise create test data manually
  - Mock `TwilioAccountManager` using Laravel's `$this->mock()` or `$this->partialMock()`

  **Must NOT do**:
  - Do NOT use Pest syntax — must be PHPUnit classes
  - Do NOT remove existing tests
  - Do NOT modify the source code — only tests

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Writing comprehensive Filament + Livewire tests requires understanding test patterns, mocking, and Filament's testing API
  - **Skills**: []
  - **Skills Evaluated but Omitted**:
    - `fluxui-development`: Testing, not UI building
    - `volt-development`: Not a Volt component

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 2
  - **Blocks**: F1, F2
  - **Blocked By**: Task 1

  **References** (CRITICAL - Be Exhaustive):

  **Pattern References**:
  - `tests/Feature/Filament/WhatsAppSetupTest.php` — Existing test file — study ALL existing test methods for tenant setup pattern, authentication, livewire assertions, and mocking conventions
  - `app/Filament/Admin/Pages/WhatsAppSetup.php` — The modified class being tested — reference the new `connectExistingWaba()` method and `waba_connection_method` property

  **API/Type References**:
  - `app/Services/TwilioAccountManager.php:280` — `connectManualWaba()` signature — mock this method's return value
  - `app/Models/WhatsAppAccount.php` — Model and factory for test data setup

  **Test References**:
  - Search for other Filament page tests in `tests/Feature/Filament/` to see authentication and tenant setup patterns

  **External References**:
  - Use `search-docs` with queries `['testing pages', 'livewire test', 'page test']` for Filament page testing patterns

  **Acceptance Criteria**:

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: New tests pass
    Tool: Bash
    Preconditions: Task 1 and Task 2 changes applied
    Steps:
      1. Run: php artisan test --compact tests/Feature/Filament/WhatsAppSetupTest.php
    Expected Result: All tests pass (0 failures)
    Failure Indicators: Any test failure or error
    Evidence: .sisyphus/evidence/task-2-tests-pass.txt

  Scenario: No existing tests broken
    Tool: Bash
    Preconditions: All changes applied
    Steps:
      1. Run: php artisan test --compact tests/Feature/Filament/WhatsAppSetupTest.php
    Expected Result: ALL tests pass including pre-existing ones
    Failure Indicators: Previously passing test now fails
    Evidence: .sisyphus/evidence/task-2-regression.txt
  ```

  **Commit**: YES
  - Message: `test(whatsapp): add tests for WABA connection paths in setup wizard`
  - Files: `tests/Feature/Filament/WhatsAppSetupTest.php`
  - Pre-commit: `php artisan test --compact tests/Feature/Filament/WhatsAppSetupTest.php`

---

- [ ] 3. Run Pint Formatter + Final Validation

  **What to do**:
  - Run `vendor/bin/pint --dirty` to fix any formatting issues across all modified files
  - Run `php -l` on all modified PHP files to verify syntax
  - Verify no new larastan errors: `vendor/bin/phpstan analyse app/Filament/Admin/Pages/WhatsAppSetup.php --memory-limit=512M` (if phpstan is configured)

  **Must NOT do**:
  - Do NOT modify business logic — formatting only
  - Do NOT modify files that were not changed in Tasks 1-2

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple formatting command — no logic, just tool execution
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES (with Task 2 in Wave 2)
  - **Parallel Group**: Wave 2 (with Task 2) — but runs after Task 1
  - **Blocks**: F1, F2
  - **Blocked By**: Task 1

  **References**:
  - Project convention: `vendor/bin/pint --dirty` per AGENTS.md and Laravel Boost guidelines

  **Acceptance Criteria**:

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Pint passes cleanly
    Tool: Bash
    Preconditions: Tasks 1-2 complete
    Steps:
      1. Run: vendor/bin/pint --dirty
      2. Run: git diff --stat (check what changed)
    Expected Result: Pint exits successfully, minimal or no formatting changes
    Failure Indicators: Large number of formatting fixes needed
    Evidence: .sisyphus/evidence/task-3-pint.txt

  Scenario: PHP syntax valid on all modified files
    Tool: Bash
    Steps:
      1. Run: php -l app/Filament/Admin/Pages/WhatsAppSetup.php
      2. Run: php -l tests/Feature/Filament/WhatsAppSetupTest.php
    Expected Result: "No syntax errors detected" for both
    Evidence: .sisyphus/evidence/task-3-syntax.txt
  ```

  **Commit**: YES (if Pint made changes)
  - Message: `style(whatsapp): apply pint formatting`
  - Files: Any files modified by Pint
  - Pre-commit: `vendor/bin/pint --test`

---

## Final Verification Wave

> 2 review agents run in PARALLEL. ALL must APPROVE. Rejection → fix → re-run.

- [ ] F1. **Plan Compliance Audit** — `deep`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read WhatsAppSetup.php, check for radio toggle, form fields, connectExistingWaba method). For each "Must NOT Have": search codebase for forbidden changes — reject if ManualWabaConnection.php was modified, if TwilioAccountManager was changed, if wizard steps were reorganized. Check evidence files exist in `.sisyphus/evidence/`. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality + Full Test Suite** — `unspecified-high`
  Run `php artisan test --compact` (full test suite). Review WhatsAppSetup.php for: proper error handling, no `as any` equivalent PHP patterns, no empty catches, no hardcoded values that should be config. Check that `connectExistingWaba()` follows same pattern as `createWaba()`. Verify notifications use proper Filament notification API. Check no N+1 queries introduced.
  Output: `Tests [N pass/N fail] | Code Quality [PASS/FAIL] | VERDICT`

---

## Commit Strategy

| Order | Message | Files | Pre-commit |
|-------|---------|-------|------------|
| 1 | `feat(whatsapp): integrate WABA connection options into setup wizard step 2` | `app/Filament/Admin/Pages/WhatsAppSetup.php` | `php -l` |
| 2 | `test(whatsapp): add tests for WABA connection paths in setup wizard` | `tests/Feature/Filament/WhatsAppSetupTest.php` | `php artisan test --compact --filter=WhatsAppSetup` |
| 3 | `style(whatsapp): apply pint formatting` (if needed) | Modified files | `vendor/bin/pint --test` |

---

## Success Criteria

### Verification Commands
```bash
php -l app/Filament/Admin/Pages/WhatsAppSetup.php  # Expected: No syntax errors
php artisan test --compact tests/Feature/Filament/WhatsAppSetupTest.php  # Expected: All pass
vendor/bin/pint --test app/Filament/Admin/Pages/WhatsAppSetup.php  # Expected: Clean
```

### Final Checklist
- [ ] Step 2 shows radio toggle between "Connect Existing WABA" and "Create New WABA"
- [ ] "Connect Existing" path shows waba_id, phone_number, meta_access_token fields
- [ ] "Create New" path shows existing createWaba button + embedded signup (unchanged)
- [ ] `connectExistingWaba()` method calls `TwilioAccountManager::connectManualWaba()` correctly
- [ ] Success flow: sets `wabaCreated = true`, advances to step 3, shows notification
- [ ] Error flow: logs error, shows danger notification, does not advance
- [ ] `ManualWabaConnection.php` is untouched
- [ ] `TwilioAccountManager.php` is untouched
- [ ] All tests pass (new + existing)
- [ ] Pint formatting clean
