# Refactor: Replace Deprecated WhatsAppTemplateService with Interface-Based Providers

## TL;DR

> **Quick Summary**: Replace 4 direct calls to deprecated `WhatsAppTemplateService` (in `WhatsAppTemplateResource.php` and `EditWhatsAppTemplate.php`) with the new interface-based providers (`TwilioWhatsAppProvider`/`MetaWhatsAppProvider`) resolved via `WhatsAppProviderFactory`, and migrate array return access to typed DTO property access.
>
> **Deliverables**:
> - `WhatsAppTemplateResource.php` — 3 table actions refactored (submit, resubmit, syncStatus)
> - `EditWhatsAppTemplate.php` — 1 header action refactored (resubmit)
> - Clean imports: `WhatsAppTemplateService` removed, `WhatsAppProviderFactory` added
>
> **Estimated Effort**: Quick (4 surgical changes in 2 files)
> **Parallel Execution**: NO — sequential (all changes are independent but small enough for a single wave)
> **Critical Path**: All changes can be done in parallel, verified in one pass

---

## Context

### Original Request
Refactor `WhatsAppTemplateResource.php` and its pages to use the new interface-based `TwilioWhatsAppProvider`/`MetaWhatsAppProvider` via `WhatsAppProviderFactory` instead of directly calling the deprecated `WhatsAppTemplateService`.

### Interview Summary
**Key Discussions**:
- 4 deprecated call sites identified across 2 files
- Provider resolution: `WhatsAppProviderFactory::create($gymId)->templates()` returns `TemplateManagementInterface`
- `submitForVerification()` returns `array` → `submitTemplate()` returns `TemplateSubmissionResult` DTO
- `syncTemplateStatus()` returns `void` in both old and new — pure substitution
- SuperAdmin Resource, Create page, List page are out of scope (no deprecated usage)

**Research Findings**:
- `WhatsAppTemplateService` is still used internally by `TwilioTemplateService`/`MetaTemplateService` for DB operations — cannot be fully removed
- `TemplateSubmissionResult` DTO has: `->success`, `->error`, `->status`, `->sid`, `->templateId` (all covered)
- `WhatsAppProviderFactory::create(int $gymId)` resolves provider from tenant's `WhatsAppAccount.provider_type`
- `Filament::getTenant()?->id` provides gym ID in resource context
- Table actions already have `$record->gym_id` guard in `visible()` closures

### Self-Review Gap Analysis
**Identified Gaps**:
- (Minor) Try-catch safety net for `WhatsAppProviderFactory::create()` throwing on missing account → resolved with try-catch in plan
- (Minor) Git merge exclusion for `.sisyphus/` directory → addressed in commit strategy
- (Ambiguous) Test strategy → default: no new tests (pure substitution), user can override

---

## Work Objectives

### Core Objective
Replace all deprecated `WhatsAppTemplateService` calls in the Admin WhatsAppTemplate Resource and Edit page with equivalent calls through the provider interface, without changing observable behavior.

### Concrete Deliverables
- `app/Filament/Admin/Resources/WhatsAppTemplateResource.php` — 3 table actions refactored, imports updated
- `app/Filament/Admin/Resources/WhatsAppTemplateResource/Pages/EditWhatsAppTemplate.php` — 1 header action refactored, imports updated

### Definition of Done
- [ ] All 4 deprecated `WhatsAppTemplateService` calls replaced with provider calls
- [ ] All `$result['...']` array access migrated to `$result->...` DTO property access
- [ ] `WhatsAppTemplateService` import removed from both files (confirmed unused elsewhere in each file)
- [ ] `WhatsAppProviderFactory` import added to both files
- [ ] `vendor/bin/pint --dirty` passes
- [ ] `composer run test` passes (existing tests)

### Must Have
- `WhatsAppTemplateService` import removed from both files
- Factory + provider calls in all 4 action handlers
- DTO property access (not array access) in notification bodies
- Error handling: provider exceptions caught, user notifications still work

### Must NOT Have (Guardrails)
- No changes to `WhatsAppTemplateService` itself (still used by providers internally)
- No changes to SuperAdmin Resource, Create page, or List page (separate scope)
- No changes to database schema or models
- No changes to `normalizeTemplateData()` (static data transformer, not a service call)
- No behavioral changes: error messages must still be shown to users
- No new dependencies introduced

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit, `composer run test`)
- **Automated tests**: No new tests (pure substitution refactor — same contract, same behavior)
- **Framework**: PHPUnit via Laravel

### QA Policy
Every task includes agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (All in parallel — 4 independent edits):
├── Task 1: Refactor WhatsAppTemplateResource submit action       [quick]
├── Task 2: Refactor WhatsAppTemplateResource resubmit action     [quick]
├── Task 3: Refactor WhatsAppTemplateResource syncStatus action   [quick]
└── Task 4: Refactor EditWhatsAppTemplate resubmit action         [quick]

Wave FINAL (sequential verification):
├── Task F1: Plan compliance audit    (oracle)
├── Task F2: Code quality review      (unspecified-high)
├── Task F3: Real manual QA          (unspecified-high)
└── Task F4: Scope fidelity check    (deep)
```

---

## TODOs

- [ ] 1. Add provider helper method + refactor `submit` table action in `WhatsAppTemplateResource.php`

  **What to do**:
  - Add `use App\Providers\WhatsAppProviderFactory;` import to the Resource
  - Add a private static helper method:
    ```php
    private static function getTemplateProvider(?int $gymId = null): TemplateManagementInterface
    {
        $gymId ??= Filament::getTenant()?->id;

        if (! $gymId) {
            throw new \RuntimeException('No tenant context available for provider resolution');
        }

        return WhatsAppProviderFactory::create($gymId)->templates();
    }
    ```
  - Add `use App\Interfaces\TemplateManagementInterface;` import
  - In the `submit` table action (line~638-656):
    - Replace `$service = app(WhatsAppTemplateService::class);` with `$provider = self::getTemplateProvider($record->gym_id);`
    - Replace `$result = $service->submitForVerification($record);` with `$result = $provider->submitTemplate($record->fresh());`
    - Replace `if ($result['success'])` with `if ($result->success)`
    - Replace `$result['error']` with `$result->error`
    - Replace `$result['status']` with `$result->status` (if used)
    - Replace `$result['sid']` with `$result->sid` (if used)
    - Wrap provider call in try-catch for `\InvalidArgumentException` and `\RuntimeException`

  **Must NOT do**:
  - Do NOT remove `WhatsAppTemplateService` import yet (other actions still use it)
  - Do NOT modify `normalizeTemplateData()` or `getDefaultTemplatesForForm()`

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Small, targeted file edits with clear before/after patterns
  - **Skills**: [`laravel-specialist`, `laravel-best-practices`]
    - `laravel-specialist`: Domain expertise in Laravel service provider pattern and Filament table actions
    - `laravel-best-practices`: Proper DI, exception handling, and code organization

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3, 4)
  - **Blocks**: Task F1-F4 (final verification depends on all tasks)
  - **Blocked By**: None (can start immediately)

  **References**:
  **Pattern References**:
  - `app/Providers/WhatsAppProviderFactory.php:create()` — Factory method signature and usage pattern
  - `app/Interfaces/TemplateManagementInterface.php:submitTemplate()` — Method signature and return type `TemplateSubmissionResult`
  
  **DTO References**:
  - `app/DTOs/WhatsAppTemplates/TemplateSubmissionResult.php` — DTO properties: `->success`, `->error`, `->status`, `->sid`, `->templateId`

  **Current Deprecated Usage (reference for replacement)**:
  - `app/Services/WhatsAppTemplateService.php:submitForVerification()` — The deprecated method being replaced

  **WHY Each Reference Matters**:
  - The factory file shows the exact `create()` signature (`int $gymId, bool $isSystemMessage = false, ?string $providerType = null`)
  - The interface file shows `submitTemplate()` returns typed DTO, not array
  - The DTO file shows all available properties for the new access pattern

  **Acceptance Criteria**:

  **QA Scenarios**:

  ```
  Scenario: Submit action uses provider factory instead of deprecated service
    Tool: Bash (grep/ast_grep)
    Preconditions: File unchanged from current state
    Steps:
      1. Run: ast_grep_search pattern='app(WhatsAppTemplateService::class)' in WhatsAppTemplateResource.php
      2. Run: ast_grep_search pattern='WhatsAppProviderFactory::create' in WhatsAppTemplateResource.php
    Expected Result: 0 matches for old service pattern (removed), 1+ matches for factory pattern (added)
    Failure Indicators: Old pattern still present, or factory pattern missing
    Evidence: .sisyphus/evidence/task-1-submit-refactor.txt

  Scenario: DTO property access replaces array access in submit action
    Tool: Bash (ast_grep)
    Preconditions: File refactored
    Steps:
      1. Search for `$result\['success'\]` in WhatsAppTemplateResource.php
      2. Search for `$result->success` in WhatsAppTemplateResource.php
    Expected Result: 0 array access matches, 1+ DTO access matches
    Failure Indicators: Array syntax still present
    Evidence: .sisyphus/evidence/task-1-dto-access.txt
  ```

  **Evidence to Capture**:
  - [ ] Grep output showing old pattern absent, new pattern present
  - [ ] DTO property access grep output

  **Commit**: NO (groups with other resource changes)

---

- [ ] 2. Refactor `resubmit` table action in `WhatsAppTemplateResource.php`

  **What to do**:
  - In the `resubmit` table action (line~667-691):
    - Replace `$service = app(WhatsAppTemplateService::class); $result = $service->submitForVerification($record);` with `$provider = self::getTemplateProvider($record->gym_id); $result = $provider->submitTemplate($record->fresh());`
    - Replace `$result['success']` with `$result->success`
    - Replace `$result['error']` with `$result->error`
    - Replace `$result['status']` with `$result->status`
    - Wrap in try-catch for safety
    - Keep the `$record->update(['status' => DRAFT, 'rejection_reason' => null])` before submission — that's UI-level state reset

  **Must NOT do**:
  - Do NOT remove the status reset before submission (it's intentional — clears rejection state before retry)

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Identical pattern to Task 1 — copy-paste with minor differences
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Familiarity with Filament table action patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3, 4)
  - **Blocks**: Task F1-F4
  - **Blocked By**: None

  **References**:
  - Same as Task 1 (uses `self::getTemplateProvider()` and `subscribeTemplate()`)
  - Also verify: `$result['status']` is accessed — DTO has `->status` property ✅

  **Acceptance Criteria**:

  **QA Scenarios**:
  ```
  Scenario: Resubmit action factory call verified
    Tool: Bash (grep)
    Steps:
      1. grep 'getTemplateProvider.*gym_id' WhatsAppTemplateResource.php
      2. grep 'submitTemplate' WhatsAppTemplateResource.php
    Expected Result: 2 matches (submit action + resubmit action)
    Evidence: .sisyphus/evidence/task-2-resubmit-refactor.txt
  ```

  **Evidence to Capture**:
  - [ ] Grep confirming 2 submitTemplate calls (submit + resubmit actions)

  **Commit**: NO (groups with other resource changes)

---

- [ ] 3. Refactor `syncStatus` table action in `WhatsAppTemplateResource.php`

  **What to do**:
  - In the `syncStatus` table action (line~702-712):
    - Replace `$service = app(WhatsAppTemplateService::class); $service->syncTemplateStatus($record);` with `$provider = self::getTemplateProvider($record->gym_id); $provider->syncTemplateStatus($record);`
    - Keep `$record->refresh()` after the call (still needed to get updated status from DB)
    - Keep the notification title/body unchanged
    - Wrap in try-catch for safety

  **Must NOT do**:
  - Do NOT change notification text (it shows dynamic status from `$record->status`)
  - Do NOT remove `$record->refresh()` (needed to reflect changes made by syncTemplateStatus)

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simplest change — pure method substitution, no DTO migration needed (both return void)
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Familiarity with Filament table action patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 4)
  - **Blocks**: Task F1-F4
  - **Blocked By**: None

  **References**:
  **Pattern References**:
  - `app/Interfaces/TemplateManagementInterface.php:syncTemplateStatus()` — Method signature (void return)
  
  **Current Deprecated Usage**:
  - `app/Services/WhatsAppTemplateService.php:syncTemplateStatus()` — Deprecated method being replaced

  **Acceptance Criteria**:

  **QA Scenarios**:
  ```
  Scenario: syncStatus action uses provider
    Tool: Bash (grep)
    Steps:
      1. Search for 'syncTemplateStatus' in WhatsAppTemplateResource.php
    Expected Result: 2 occurrences (the ProviderInterface::syncTemplateStatus call + the method reference)
    Failure Indicators: More than 2 occurrences (old service pattern still present)
    Evidence: .sisyphus/evidence/task-3-syncstatus-refactor.txt
  ```

  **Evidence to Capture**:
  - [ ] Grep confirming syncTemplateStatus call uses provider, not old service
  - [ ] Confirmed `$record->refresh()` still present

  **Commit**: NO (groups with other resource changes)

---

- [ ] 4. Refactor `resubmit` header action in `EditWhatsAppTemplate.php`

  **What to do**:
  - Add `use App\Providers\WhatsAppProviderFactory;` import
  - Remove `use App\Services\WhatsAppTemplateService;` import
  - In the `resubmit` header action (line~31-58), replace:
    ```php
    $service = app(WhatsAppTemplateService::class);
    $result = $service->submitForVerification($this->record);
    ```
    with:
    ```php
    $provider = WhatsAppProviderFactory::create($this->record->gym_id)->templates();
    $result = $provider->submitTemplate($this->record->fresh());
    ```
  - Replace `$result['success']` with `$result->success`
  - Replace `$result['error']` with `$result->error`
  - Wrap in try-catch for `\InvalidArgumentException` and `\RuntimeException`
  - In the `submit` header action (line~61-90), apply the same change pattern
  - After confirming all other actions in the file are updated, remove the `WhatsAppTemplateService` import

  **Must NOT do**:
  - Do NOT touch the Create page or List page
  - Do NOT try to use `self::getTemplateProvider()` — that's on the Resource class, not the Page (use factory directly)

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Same pattern as Tasks 1-2, single file with 2 action closures
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Filament Page action patterns, Volt/Livewire component structure

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 3)
  - **Blocks**: Task F1-F4
  - **Blocked By**: None

  **References**:
  **Pattern References**:
  - `app/Providers/WhatsAppProviderFactory.php:create()` — Direct usage (no helper in Page class)
  - `app/Interfaces/TemplateManagementInterface.php:submitTemplate()` — Method signature and return type

  **DTO References**:
  - `app/DTOs/WhatsAppTemplates/TemplateSubmissionResult.php` — Properties for migration from array

  **Acceptance Criteria**:

  **QA Scenarios**:
  ```
  Scenario: Edit page no longer imports deprecated service
    Tool: Bash (grep)
    Steps:
      1. grep 'WhatsAppTemplateService' EditWhatsAppTemplate.php
      2. grep 'WhatsAppProviderFactory' EditWhatsAppTemplate.php
    Expected Result: 0 matches for old import, 1+ matches for factory
    Failure Indicators: Old import still present
    Evidence: .sisyphus/evidence/task-4-edit-imports.txt

  Scenario: Edit page actions use provider
    Tool: Bash (grep)
    Steps:
      1. grep 'submitTemplate' EditWhatsAppTemplate.php
    Expected Result: 2 matches (resubmit + submit header actions)
    Failure Indicators: 0 matches or old pattern present
    Evidence: .sisyphus/evidence/task-4-edit-actions.txt
  ```

  **Evidence to Capture**:
  - [ ] Grep confirming imports swapped
  - [ ] Grep confirming 2 submitTemplate calls

  **Commit**: YES (groups all Task 1-4 changes)
  - Message: `refactor(whatsapp): replace deprecated WhatsAppTemplateService with interface-based providers`
  - Files:
    - `app/Filament/Admin/Resources/WhatsAppTemplateResource.php`
    - `app/Filament/Admin/Resources/WhatsAppTemplateResource/Pages/EditWhatsAppTemplate.php`
  - Pre-commit: `composer run test --filter=WhatsAppTemplate`

---

## Final Verification Wave

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read source files, grep for key patterns). For each "Must NOT Have": search codebase for forbidden patterns — reject with file:line if found. Check evidence files exist in `.sisyphus/evidence/`. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `php -l` on both changed files, `vendor/bin/pint --dirty`, and `composer run test --filter=WhatsAppTemplate`. Check for: unused imports, commented-out code, missing try-catch, console.log/var_dump in prod code.
  Output: `Lint [PASS/FAIL] | Pint [PASS/FAIL] | Tests [N pass/N fail] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Run grep/ast_grep on both files. Verify: (a) no `WhatsAppTemplateService` import remains, (b) `WhatsAppProviderFactory` import added, (c) 4 `submitTemplate` calls present across both files (3 in resource + 1 in edit), (d) 1 `syncTemplateStatus` call present in resource, (e) array access `$result[` replaced with object access `$result->` in all action closures.
  Output: `Imports [N/N correct] | Calls [N/N correct] | Access [N/N migrated] | VERDICT: APPROVE/REJECT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff (git diff). Verify 1:1 — everything in spec was built, nothing beyond spec was built. Check "Must NOT do" compliance: no changes to SuperAdmin, Create page, List page, database, models, or `normalizeTemplateData()`. Detect cross-task contamination.
  Output: `Tasks [N/N compliant] | Scope creep [CLEAN/N issues] | VERDICT`

---

## Commit Strategy

| Task | Commit? | Message | Files |
|------|---------|---------|-------|
| 1-4 | YES (grouped) | `refactor(whatsapp): replace deprecated WhatsAppTemplateService with interface-based providers` | `WhatsAppTemplateResource.php`, `EditWhatsAppTemplate.php` |

Pre-commit: `composer run test --filter=WhatsAppTemplate`

---

## Success Criteria

### Verification Commands
```bash
# Verify no deprecated service import in Resource
grep -n "WhatsAppTemplateService" app/Filament/Admin/Resources/WhatsAppTemplateResource.php
# Expected: no output (import removed)

# Verify no deprecated service import in Edit page
grep -n "WhatsAppTemplateService" app/Filament/Admin/Resources/WhatsAppTemplateResource/Pages/EditWhatsAppTemplate.php
# Expected: no output (import removed)

# Verify factory import in Resource
grep -n "WhatsAppProviderFactory" app/Filament/Admin/Resources/WhatsAppTemplateResource.php
# Expected: 1 match (use import)

# Verify factory import in Edit page
grep -n "WhatsAppProviderFactory" app/Filament/Admin/Resources/WhatsAppTemplateResource/Pages/EditWhatsAppTemplate.php
# Expected: 1 match (use import)

# Verify submitTemplate usage
grep -n "submitTemplate" app/Filament/Admin/Resources/WhatsAppTemplateResource.php app/Filament/Admin/Resources/WhatsAppTemplateResource/Pages/EditWhatsAppTemplate.php
# Expected: 4 matches across both files (3 in resource + 1 in edit)

# Verify syncTemplateStatus usage
grep -n "syncTemplateStatus" app/Filament/Admin/Resources/WhatsAppTemplateResource.php
# Expected: 1 match (provider call in syncStatus action)

# Verify no array-style access remains
grep -n "\$result\[" app/Filament/Admin/Resources/WhatsAppTemplateResource.php app/Filament/Admin/Resources/WhatsAppTemplateResource/Pages/EditWhatsAppTemplate.php
# Expected: 0 matches (all migrated to -> property access)

# Verify tests pass
composer run test --filter=WhatsAppTemplate
# Expected: tests pass

# Verify Pint passes
vendor/bin/pint --dirty
# Expected: no formatting changes needed
```

### Final Checklist
- [ ] All 4 deprecated calls replaced
- [ ] All array access migrated to DTO property access
- [ ] `WhatsAppTemplateService` import removed from both files
- [ ] `WhatsAppProviderFactory` import added to both files
- [ ] `TemplateManagementInterface` import added to Resource
- [ ] `self::getTemplateProvider()` helper method added to Resource
- [ ] Pint passes on all changed files
- [ ] Existing tests pass
- [ ] No unintended changes to SuperAdmin, Create, List, models, DB
