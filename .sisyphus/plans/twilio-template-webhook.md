# Twilio Template Status Webhook Implementation

## TL;DR

> **Quick Summary**: Replace deprecated polling commands with a real-time Twilio webhook handler that receives template status updates, processes them asynchronously via a queued job, and updates the database instantly — mirroring the existing Meta webhook pattern while applying DRY principles.
>
> **Deliverables**:
> - New `TwilioTemplateWebhookController` with GET verification + POST handler
> - New `ProcessTwilioTemplateWebhook` queued job
> - Extracted `WhatsAppTemplateStatusMapper` shared service (DRY)
> - New API routes under `/api/webhooks/twilio/templates`
> - Feature + unit tests for controller and job
> - Deprecation of `SyncWhatsAppTemplates` and `SyncWhatsAppTemplateStatusesCommand`
>
> **Estimated Effort**: Medium
> **Parallel Execution**: YES - 3 waves
> **Critical Path**: T1 (status mapper) → T2, T3 (controller + job) → T4 (routes + tests) → T5 (deprecation)

---

## Context

### Original Request
Add a Twilio webhook handler that receives template status updates from Twilio WhatsApp API, update the database in real-time instead of relying on polling. Follow DRY principle. Reference existing Meta webhook implementation files.

### Interview Summary
**Key Discussions**:
- User wants real-time template status updates via webhook instead of polling
- Existing Meta webhook: `WhatsAppTemplateWebhookController` (GET verify + POST handle with HMAC validation) → `ProcessMetaTemplateWebhook` job (ShouldBeUnique, ShouldQueue, status mapping, notifications)
- Existing Twilio webhook: `TwilioWebhookController` handles message delivery status (not template status), uses `RequestValidator` for signature validation
- Deprecated polling commands: `SyncWhatsAppTemplates`, `SyncWhatsAppTemplateStatusesCommand`
- Both Meta and Twilio use similar status values (APPROVED, REJECTED, PENDING, DISABLED)
- Same notification classes used for both providers

**Research Findings**:
- Twilio uses `X-Twilio-Signature` header validated via `Twilio\Security\RequestValidator`
- Twilio template webhook payload includes `ContentSid`, `Status`, `ErrorCode`, `ErrorMessage`
- Template lookup for Twilio: `WhatsAppTemplate::where('twilio_template_sid', $contentSid)->first()`
- `TwilioTemplateService::mapStatus()` already exists but is private — extract to shared service
- `ProcessMetaTemplateWebhook::mapStatus()` duplicates the same logic — both should use shared mapper
- Both jobs follow identical pattern: lookup template, map status, skip if unchanged, update, notify

### Metis Review
**Identified Gaps** (addressed):
- Need to handle Twilio webhook verification (GET) differently from Meta — Twilio doesn't use hub_challenge pattern, uses simple token match in config
- Need to decide route structure: `/webhooks/twilio/templates` vs `/webhooks/whatsapp/templates/twilio` — chose `/webhooks/twilio/templates` for consistency with existing `/webhooks/twilio/status`
- Need to ensure the shared status mapper handles both Meta events and Twilio status strings
- Need to mark polling commands as deprecated rather than deleting them (backward compatibility)

---

## Work Objectives

### Core Objective
Implement real-time Twilio template status webhook handling with DRY shared status mapping, replacing the deprecated polling approach.

### Concrete Deliverables
- `app/Http/Controllers/Api/TwilioTemplateWebhookController.php` — new controller
- `app/Jobs/ProcessTwilioTemplateWebhook.php` — new queued job
- `app/Services/Support/WhatsAppTemplateStatusMapper.php` — shared status mapping service
- `routes/api.php` — new webhook routes
- `tests/Feature/Http/Controllers/Api/TwilioTemplateWebhookTest.php` — controller tests
- `tests/Unit/Jobs/ProcessTwilioTemplateWebhookTest.php` — job tests
- Deprecation annotations on `SyncWhatsAppTemplates` and `SyncWhatsAppTemplateStatusesCommand`

### Definition of Done
- [ ] `php artisan test --filter=TwilioTemplate` passes (all new tests)
- [ ] `php artisan test --filter=ProcessTwilioTemplateWebhook` passes
- [ ] `php artisan test --filter=WhatsAppTemplateStatusMapper` passes
- [ ] `vendor/bin/pint --dirty` clean
- [ ] `lsp_diagnostics` clean on all new files
- [ ] Webhook endpoint responds 200 to valid Twilio POST with signature
- [ ] Webhook endpoint responds 403 to invalid signature
- [ ] Job updates template status and sends notification on APPROVED event
- [ ] Polling commands show deprecation warning when run

### Must Have
- Twilio signature validation using `RequestValidator`
- Async job dispatch with `ShouldBeUnique` to prevent duplicate processing
- Shared status mapper used by both Meta and Twilio jobs (DRY)
- Cache-based template/account lookup (follow Meta pattern)
- Notification sending on status change (reuse existing notification classes)
- Rate limiting on POST endpoint
- Generic error messages to external callers (security)

### Must NOT Have (Guardrails)
- No raw API errors exposed in webhook responses
- No modification to existing `TwilioWebhookController` (message delivery) — separate controller
- No deletion of polling commands — only deprecation annotations + log warnings
- No changes to Meta webhook implementation
- No `env()` calls outside config files
- No `declare(strict_types=1)` omission on new files

---

## Verification Strategy (MANDATORY)

> **ZERO HUMAN INTERVENTION** - ALL verification is agent-executed. No exceptions.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: TDD
- **Framework**: PHPUnit
- **If TDD**: Each task follows RED (failing test) → GREEN (minimal impl) → REFACTOR

### QA Policy
Every task MUST include agent-executed QA scenarios.
Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **API/Backend**: Use Bash (curl) - Send requests, assert status + response fields
- **Library/Module**: Use Bash (php artisan test) - Run targeted tests

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Start Immediately - shared foundation):
├── Task 1: Extract shared WhatsAppTemplateStatusMapper service [quick]
└── Task 2: Refactor ProcessMetaTemplateWebhook to use shared mapper [quick]

Wave 2 (After Wave 1 - core Twilio webhook implementation, MAX PARALLEL):
├── Task 3: Create TwilioTemplateWebhookController [deep]
├── Task 4: Create ProcessTwilioTemplateWebhook job [deep]
└── Task 5: Add webhook routes + config [quick]

Wave 3 (After Wave 2 - tests + deprecation):
├── Task 6: Controller feature tests [unspecified-high]
├── Task 7: Job unit tests [unspecified-high]
├── Task 8: Status mapper unit tests [quick]
└── Task 9: Deprecate polling commands [quick]

Wave FINAL (After ALL tasks — 4 parallel reviews, then user okay):
├── Task F1: Plan compliance audit (oracle)
├── Task F2: Code quality review (unspecified-high)
├── Task F3: Real manual QA (unspecified-high)
└── Task F4: Scope fidelity check (deep)
-> Present results -> Get explicit user okay
```

### Dependency Matrix

- **1-2**: - - 3, 4, 6, 7, 8
- **3**: 1 - 5, 6
- **4**: 1, 2 - 7
- **5**: 3 - 6
- **6**: 3, 5 - FINAL
- **7**: 4 - FINAL
- **8**: 1 - FINAL
- **9**: - - FINAL (independent)

### Agent Dispatch Summary

- **Wave 1**: **2** - T1 → `quick`, T2 → `quick`
- **Wave 2**: **3** - T3 → `deep`, T4 → `deep`, T5 → `quick`
- **Wave 3**: **4** - T6 → `unspecified-high`, T7 → `unspecified-high`, T8 → `quick`, T9 → `quick`
- **FINAL**: **4** - F1 → `oracle`, F2 → `unspecified-high`, F3 → `unspecified-high`, F4 → `deep`

---

## TODOs

> Implementation + Test = ONE Task. Never separate.
> EVERY task MUST have: Recommended Agent Profile + Parallelization info + QA Scenarios.

- [ ] 1. Extract shared WhatsAppTemplateStatusMapper service

  **What to do**:
  - Create `app/Services/Support/WhatsAppTemplateStatusMapper.php`
  - Implement `mapStatus(string $status): ?WhatsAppTemplateStatusEnum` handling both Meta events (APPROVED, REJECTED, PENDING, PENDING_DELETION, FLAGGED, PAUSED, DISABLED) and Twilio statuses (approved, rejected, pending, disabled, paused)
  - Case-insensitive matching with `strtoupper()`
  - Add `declare(strict_types=1)`
  - Unit test the mapper with all status variants

  **Must NOT do**:
  - No provider-specific logic in the mapper (it should be purely status→enum mapping)
  - No database access or side effects

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Single file creation with straightforward mapping logic
  - **Skills**: [`laravel-best-practices`]
    - `laravel-best-practices`: Follow Laravel service class conventions, explicit return types, PHPDoc

  **Parallelization**:
  - **Can Run In Parallel**: NO (blocks T2, T3, T4, T8)
  - **Parallel Group**: Wave 1 (with Task 2)
  - **Blocks**: Tasks 2, 3, 4, 6, 7, 8
  - **Blocked By**: None (can start immediately)

  **References**:
  - `app/Jobs/ProcessMetaTemplateWebhook.php:192-204` - Current `mapStatus()` method to extract
  - `app/Services/Twilio/TwilioTemplateService.php:446-455` - Twilio's `mapStatus()` to unify
  - `app/Enums/WhatsAppTemplateStatusEnum.php` - Target enum with all cases

  **Acceptance Criteria**:
  - [ ] `app/Services/Support/WhatsAppTemplateStatusMapper.php` exists with `mapStatus()` method
  - [ ] `php artisan test --filter=WhatsAppTemplateStatusMapper` passes (all status variants tested)

  **QA Scenarios**:
  ```
  Scenario: Happy path - maps all known Meta events correctly
    Tool: Bash (php artisan test)
    Steps:
      1. Run: php artisan test --filter=WhatsAppTemplateStatusMapper
      2. Assert: All tests pass with 0 failures
    Expected Result: Tests cover APPROVED, REJECTED, PENDING, PENDING_DELETION, FLAGGED, PAUSED, DISABLED → correct enum values
    Evidence: .sisyphus/evidence/task-1-meta-status-mapping.log

  Scenario: Happy path - maps all known Twilio statuses correctly
    Tool: Bash (php artisan test)
    Steps:
      1. Run: php artisan test --filter=WhatsAppTemplateStatusMapper
      2. Assert: approved→APPROVED, rejected→REJECTED, pending→PENDING, disabled→DISABLED, paused→DISABLED
    Expected Result: All Twilio status strings map to correct enum values
    Evidence: .sisyphus/evidence/task-1-twilio-status-mapping.log

  Scenario: Edge case - returns null for unknown status
    Tool: Bash (php artisan test)
    Steps:
      1. Call mapper with 'UNKNOWN_STATUS'
      2. Assert: returns null
    Expected Result: Unknown status returns null without throwing exception
    Evidence: .sisyphus/evidence/task-1-unknown-status.log
  ```

  **Evidence to Capture**:
  - [ ] Test output log showing all status mappings pass
  - [ ] Test output log showing unknown status returns null

  **Commit**: YES (groups with 2)
  - Message: `feat(webhook): extract shared WhatsAppTemplateStatusMapper for DRY status mapping`
  - Files: `app/Services/Support/WhatsAppTemplateStatusMapper.php`, `tests/Unit/Services/Support/WhatsAppTemplateStatusMapperTest.php`
  - Pre-commit: `php artisan test --filter=WhatsAppTemplateStatusMapper`

- [ ] 2. Refactor ProcessMetaTemplateWebhook to use shared mapper

  **What to do**:
  - Update `ProcessMetaTemplateWebhook` to inject/use `WhatsAppTemplateStatusMapper`
  - Remove private `mapStatus()` method (lines 192-204)
  - Replace `$this->mapStatus($this->event)` with `WhatsAppTemplateStatusMapper::mapStatus($this->event)` or injected instance
  - Keep all other logic unchanged (cache lookup, notification, etc.)

  **Must NOT do**:
  - No changes to job constructor signature (backward compatible)
  - No changes to notification logic
  - No changes to cache lookup logic

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Single file edit — replace method call and remove private method
  - **Skills**: [`laravel-best-practices`]
    - `laravel-best-practices`: Follow Laravel service injection patterns

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T1)
  - **Parallel Group**: Wave 1 (with Task 1, after T1 completes)
  - **Blocks**: Task 4, 7
  - **Blocked By**: Task 1

  **References**:
  - `app/Jobs/ProcessMetaTemplateWebhook.php:98-108` - Current mapStatus usage at line 99
  - `app/Jobs/ProcessMetaTemplateWebhook.php:192-204` - Private mapStatus method to remove
  - `app/Services/Support/WhatsAppTemplateStatusMapper.php` (Task 1 output) - New shared mapper

  **Acceptance Criteria**:
  - [ ] `ProcessMetaTemplateWebhook` uses `WhatsAppTemplateStatusMapper` instead of private `mapStatus()`
  - [ ] `php artisan test --filter=ProcessMetaTemplateWebhook` still passes (behavior unchanged)
  - [ ] No `mapStatus` method exists in `ProcessMetaTemplateWebhook`

  **QA Scenarios**:
  ```
  Scenario: Regression test - job still processes status updates correctly
    Tool: Bash (php artisan test)
    Steps:
      1. Run: php artisan test --filter=ProcessMetaTemplateWebhook
      2. Assert: All existing tests pass with 0 failures
    Expected Result: Job behavior unchanged — still maps statuses, updates templates, sends notifications
    Evidence: .sisyphus/evidence/task-2-regression-tests.log

  Scenario: Verify mapStatus method removed from job
    Tool: Bash (grep)
    Steps:
      1. Run: grep -n "function mapStatus" app/Jobs/ProcessMetaTemplateWebhook.php
      2. Assert: No output (method removed)
    Expected Result: Private mapStatus method no longer exists in the job
    Evidence: .sisyphus/evidence/task-2-method-removed.log
  ```

  **Evidence to Capture**:
  - [ ] Test output showing all existing ProcessMetaTemplateWebhook tests pass
  - [ ] Grep output confirming mapStatus method removed

  **Commit**: YES (groups with 1)
  - Message: `refactor(webhook): use shared status mapper in ProcessMetaTemplateWebhook`
  - Files: `app/Jobs/ProcessMetaTemplateWebhook.php`
  - Pre-commit: `php artisan test --filter=ProcessMetaTemplateWebhook`

- [ ] 3. Create TwilioTemplateWebhookController

  **What to do**:
  - Create `app/Http/Controllers/Api/TwilioTemplateWebhookController.php`
  - Implement `verifyWebhook(Request $request)` — GET handler for webhook verification
    - Twilio doesn't use hub_challenge like Meta; verify via config token match
    - Read `config('services.twilio.webhook_verify_token')` and compare with request param
  - Implement `handleTemplateStatusWebhook(Request $request)` — POST handler
    - Validate Twilio signature using `RequestValidator` (reuse pattern from `TwilioWebhookController`)
    - Parse payload: extract `ContentSid`, `MessageStatus`, `ErrorCode`, `ErrorMessage`
    - Dispatch `ProcessTwilioTemplateWebhook` job asynchronously
    - Return 200 JSON response
    - Log all events appropriately
  - Add `declare(strict_types=1)`
  - Generic error messages for external callers (security)

  **Must NOT do**:
  - No direct database updates in controller (delegate to job)
  - No raw error messages in responses
  - No modification to existing `TwilioWebhookController`

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: New controller with signature validation, payload parsing, error handling, and security considerations
  - **Skills**: [`laravel-best-practices`, `laravel-security`]
    - `laravel-best-practices`: Follow Laravel controller conventions, explicit types, Form Request if needed
    - `laravel-security`: Signature validation, generic error messages, no env() calls

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T1 for status mapper reference)
  - **Parallel Group**: Wave 2 (with Tasks 4, 5)
  - **Blocks**: Task 5, 6
  - **Blocked By**: Task 1

  **References**:
  - `app/Http/Controllers/Api/WhatsAppTemplateWebhookController.php` — Meta webhook controller pattern to follow
  - `app/Http/Controllers/Api/TwilioWebhookController.php:84-100` — Existing Twilio signature validation pattern
  - `app/Jobs/ProcessMetaTemplateWebhook.php` — Job dispatch pattern to mirror
  - `config/services.php:90-118` — Twilio config section (need to add webhook_verify_token)

  **Acceptance Criteria**:
  - [ ] GET `/api/webhooks/twilio/templates` returns 200 with valid verify token
  - [ ] GET `/api/webhooks/twilio/templates` returns 403 with invalid/missing token
  - [ ] POST `/api/webhooks/twilio/templates` returns 200 with valid signature and dispatches job
  - [ ] POST `/api/webhooks/twilio/templates` returns 403 with invalid signature
  - [ ] POST `/api/webhooks/twilio/templates` returns 401 with missing signature
  - [ ] `php artisan test --filter=TwilioTemplateWebhook` passes

  **QA Scenarios**:
  ```
  Scenario: GET webhook verification succeeds with valid token
    Tool: Bash (curl)
    Steps:
      1. Run: curl -s -o /dev/null -w "%{http_code}" "http://localhost/api/webhooks/twilio/templates?verify_token=correct_token"
      2. Assert: HTTP 200
    Expected Result: Webhook verification succeeds
    Evidence: .sisyphus/evidence/task-3-get-verify-success.log

  Scenario: GET webhook verification fails with invalid token
    Tool: Bash (curl)
    Steps:
      1. Run: curl -s -o /dev/null -w "%{http_code}" "http://localhost/api/webhooks/twilio/templates?verify_token=wrong_token"
      2. Assert: HTTP 403
    Expected Result: Webhook verification fails with 403
    Evidence: .sisyphus/evidence/task-3-get-verify-fail.log

  Scenario: POST webhook rejects missing signature
    Tool: Bash (curl)
    Steps:
      1. Run: curl -s -X POST http://localhost/api/webhooks/twilio/templates -H "Content-Type: application/json" -d '{"ContentSid":"HX123","MessageStatus":"approved"}'
      2. Assert: HTTP 401 and response contains "Missing signature"
    Expected Result: Request rejected with 401
    Evidence: .sisyphus/evidence/task-3-post-missing-sig.log

  Scenario: POST webhook processes valid template status update
    Tool: Bash (curl)
    Steps:
      1. Run: curl -s -X POST http://localhost/api/webhooks/twilio/templates -H "Content-Type: application/json" -H "X-Twilio-Signature: valid_sig" -d '{"ContentSid":"HX123","MessageStatus":"approved"}'
      2. Assert: HTTP 200 and response contains "success"
      3. Check Laravel log for "Twilio template status update" entry
    Expected Result: Webhook accepted, job dispatched, log entry created
    Evidence: .sisyphus/evidence/task-3-post-valid.log
  ```

  **Evidence to Capture**:
  - [ ] curl output for each scenario (HTTP status codes, response bodies)
  - [ ] Laravel log entries showing webhook processing

  **Commit**: YES (groups with 4, 5)
  - Message: `feat(webhook): add TwilioTemplateWebhookController for real-time template status updates`
  - Files: `app/Http/Controllers/Api/TwilioTemplateWebhookController.php`
  - Pre-commit: `php -l app/Http/Controllers/Api/TwilioTemplateWebhookController.php`

- [ ] 4. Create ProcessTwilioTemplateWebhook job

  **What to do**:
  - Create `app/Jobs/ProcessTwilioTemplateWebhook.php`
  - Implement `ShouldBeUnique`, `ShouldQueue` (follow `ProcessMetaTemplateWebhook` pattern)
  - Constructor: `public function __construct(public string $contentSid, public string $status, public ?string $errorCode = null, public ?string $errorMessage = null)`
  - `uniqueId()`: return `$this->contentSid`
  - `handle()`:
    - Find template by `twilio_template_sid` = `$contentSid`
    - Use `WhatsAppTemplateStatusMapper::mapStatus()` for status mapping
    - Skip if status unchanged
    - Update template status and rejection_reason if rejected
    - Send notification (reuse existing notification classes)
    - Cache template lookup (follow Meta pattern: `Cache::flexible`)
  - `$tries = 3`, `$backoff = 30`, `$timeout = 120`
  - `failed()` method for error logging
  - Add `declare(strict_types=1)`

  **Must NOT do**:
  - No direct API calls to Twilio (webhook is the source of truth)
  - No duplicate status mapping logic (use shared mapper)
  - No raw error exposure

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Queued job with cache logic, database operations, notification dispatch, error handling
  - **Skills**: [`laravel-best-practices`, `laravel-specialist`]
    - `laravel-best-practices`: Queue job conventions, ShouldBeUnique, proper typing
    - `laravel-specialist`: Eloquent queries, cache patterns, notification dispatch

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T1, T2)
  - **Parallel Group**: Wave 2 (with Tasks 3, 5)
  - **Blocks**: Task 7
  - **Blocked By**: Tasks 1, 2

  **References**:
  - `app/Jobs/ProcessMetaTemplateWebhook.php` — Full pattern to mirror (ShouldBeUnique, cache lookup, notification, failed handler)
  - `app/Services/Support/WhatsAppTemplateStatusMapper.php` (Task 1 output) — Shared status mapper
  - `app/Models/WhatsAppTemplate.php:26-27` — `twilio_template_sid` field for lookup
  - `app/Notifications/WhatsAppTemplateApprovedNotification.php` — Reuse for approved notification
  - `app/Notifications/WhatsAppTemplateRejectedNotification.php` — Reuse for rejected notification
  - `app/Notifications/WhatsAppTemplateStatusChangedNotification.php` — Reuse for other status changes

  **Acceptance Criteria**:
  - [ ] Job class implements `ShouldBeUnique` and `ShouldQueue`
  - [ ] `uniqueId()` returns `contentSid`
  - [ ] Job finds template by `twilio_template_sid`
  - [ ] Job uses `WhatsAppTemplateStatusMapper::mapStatus()`
  - [ ] Job skips if status unchanged
  - [ ] Job sends correct notification based on new status
  - [ ] `php artisan test --filter=ProcessTwilioTemplateWebhook` passes

  **QA Scenarios**:
  ```
  Scenario: Happy path - job processes approved template status
    Tool: Bash (php artisan test)
    Steps:
      1. Create test: gym, whatsapp_account, whatsapp_template with twilio_template_sid='HX123', status=PENDING
      2. Dispatch: ProcessTwilioTemplateWebhook('HX123', 'approved')
      3. Assert: template.status === WhatsAppTemplateStatusEnum::APPROVED
      4. Assert: notification was sent to gym owner
    Expected Result: Template status updated to APPROVED, notification sent
    Evidence: .sisyphus/evidence/task-4-approved-status.log

  Scenario: Happy path - job processes rejected template status with reason
    Tool: Bash (php artisan test)
    Steps:
      1. Create test: gym, whatsapp_account, whatsapp_template with twilio_template_sid='HX456', status=PENDING
      2. Dispatch: ProcessTwilioTemplateWebhook('HX456', 'rejected', '1234', 'Policy violation')
      3. Assert: template.status === WhatsAppTemplateStatusEnum::REJECTED
      4. Assert: template.rejection_reason === 'Policy violation'
    Expected Result: Template rejected with reason stored
    Evidence: .sisyphus/evidence/task-4-rejected-status.log

  Scenario: Edge case - job skips when status unchanged
    Tool: Bash (php artisan test)
    Steps:
      1. Create test: template with status=APPROVED
      2. Dispatch: ProcessTwilioTemplateWebhook('HX789', 'approved')
      3. Assert: template not modified (check updated_at unchanged)
      4. Assert: log contains "status unchanged, skipping"
    Expected Result: No update performed, log entry created
    Evidence: .sisyphus/evidence/task-4-unchanged-status.log

  Scenario: Edge case - job handles template not found
    Tool: Bash (php artisan test)
    Steps:
      1. Dispatch: ProcessTwilioTemplateWebhook('HX_NONEXISTENT', 'approved')
      2. Assert: no exception thrown
      3. Assert: log contains "template not found"
    Expected Result: Graceful handling, warning logged
    Evidence: .sisyphus/evidence/task-4-template-not-found.log

  Scenario: Failure case - job failure is logged properly
    Tool: Bash (php artisan test)
    Steps:
      1. Dispatch job that will fail (e.g., database connection error simulation)
      2. Assert: failed() method logs error with contentSid and error message
    Expected Result: Error logged with full context
    Evidence: .sisyphus/evidence/task-4-job-failure.log
  ```

  **Evidence to Capture**:
  - [ ] Test output showing all scenarios pass
  - [ ] Log entries for each scenario

  **Commit**: YES (groups with 3, 5)
  - Message: `feat(webhook): add ProcessTwilioTemplateWebhook job for async template status processing`
  - Files: `app/Jobs/ProcessTwilioTemplateWebhook.php`
  - Pre-commit: `php -l app/Jobs/ProcessTwilioTemplateWebhook.php`

- [ ] 5. Add webhook routes and config

  **What to do**:
  - Add routes to `routes/api.php` under existing `webhooks` prefix:
    - `Route::get('/twilio/templates', [TwilioTemplateWebhookController::class, 'verifyWebhook'])`
    - `Route::post('/twilio/templates', [TwilioTemplateWebhookController::class, 'handleTemplateStatusWebhook'])->middleware('throttle:60,1')`
  - Add `webhook_verify_token` to `config/services.php` under `twilio` section:
    - `'webhook_verify_token' => env('TWILIO_WEBHOOK_VERIFY_TOKEN'),`
  - Add `TWILIO_WEBHOOK_VERIFY_TOKEN` to `.env.example` if it exists

  **Must NOT do**:
  - No changes to existing routes
  - No changes to existing config keys

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple route additions and config key addition
  - **Skills**: [`laravel-best-practices`]
    - `laravel-best-practices`: Route naming conventions, config structure

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T3 for controller reference)
  - **Parallel Group**: Wave 2 (with Tasks 3, 4)
  - **Blocks**: Task 6
  - **Blocked By**: Task 3

  **References**:
  - `routes/api.php:46-58` — Existing webhook route structure to follow
  - `routes/api.php:74-78` — Existing Twilio status route
  - `config/services.php:90-118` — Twilio config section

  **Acceptance Criteria**:
  - [ ] `php artisan route:list --path=webhooks/twilio/templates` shows GET and POST routes
  - [ ] `config('services.twilio.webhook_verify_token')` returns configured value
  - [ ] `.env.example` contains `TWILIO_WEBHOOK_VERIFY_TOKEN=`

  **QA Scenarios**:
  ```
  Scenario: Routes are registered correctly
    Tool: Bash (php artisan)
    Steps:
      1. Run: php artisan route:list --path=webhooks/twilio/templates
      2. Assert: Output shows GET and POST routes with correct controller methods
    Expected Result: Both routes appear with correct HTTP methods and controller
    Evidence: .sisyphus/evidence/task-5-routes-registered.log

  Scenario: Config key is accessible
    Tool: Bash (php artisan tinker)
    Steps:
      1. Run: php artisan tinker --execute="echo config('services.twilio.webhook_verify_token');"
      2. Assert: Output is not null (returns env value or null if not set)
    Expected Result: Config key accessible without error
    Evidence: .sisyphus/evidence/task-5-config-accessible.log
  ```

  **Evidence to Capture**:
  - [ ] Route list output
  - [ ] Config access output

  **Commit**: YES (groups with 3, 4)
  - Message: `feat(webhook): add Twilio template webhook routes and config`
  - Files: `routes/api.php`, `config/services.php`, `.env.example`
  - Pre-commit: `php artisan route:list --path=webhooks/twilio/templates`

- [ ] 6. Controller feature tests

  **What to do**:
  - Create `tests/Feature/Http/Controllers/Api/TwilioTemplateWebhookTest.php`
  - Test GET verification: valid token → 200, invalid token → 403, missing token → 403
  - Test POST webhook: valid signature → 200 + job dispatched, invalid signature → 403, missing signature → 401
  - Test POST payload: valid ContentSid + status → job dispatched with correct params
  - Test POST payload: missing ContentSid → 200 (graceful, log warning)
  - Use `Queue::fake()` to verify job dispatch
  - Use `RefreshDatabase` trait
  - Follow existing `WhatsAppTemplateWebhookTest.php` patterns

  **Must NOT do**:
  - No unit tests (those are in Task 7)
  - No mocking of Twilio SDK (webhook doesn't call Twilio API)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Feature tests require understanding of HTTP testing, queue faking, signature generation
  - **Skills**: [`phpunit-best-practices`, `laravel-security`]
    - `phpunit-best-practices`: PHPUnit test structure, assertions, data providers
    - `laravel-security`: Test security scenarios (invalid signatures, missing headers)

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T3, T5)
  - **Parallel Group**: Wave 3 (with Tasks 7, 8, 9)
  - **Blocks**: FINAL wave
  - **Blocked By**: Tasks 3, 5

  **References**:
  - `tests/Feature/Http/Controllers/Api/WhatsAppTemplateWebhookTest.php` — Meta webhook test patterns
  - `app/Http/Controllers/Api/TwilioWebhookController.php` — Existing Twilio signature validation for test reference
  - `app/Jobs/ProcessTwilioTemplateWebhook.php` (Task 4 output) — Job class to verify dispatch

  **Acceptance Criteria**:
  - [ ] All feature tests pass: `php artisan test --filter=TwilioTemplateWebhookTest`
  - [ ] Tests cover: GET verify (3 scenarios), POST signature validation (3 scenarios), POST payload processing (3 scenarios)
  - [ ] Queue::fake() verifies correct job dispatch with correct parameters

  **QA Scenarios**:
  ```
  Scenario: All controller feature tests pass
    Tool: Bash (php artisan test)
    Steps:
      1. Run: php artisan test --filter=TwilioTemplateWebhookTest
      2. Assert: All tests pass, 0 failures, 0 errors
    Expected Result: Complete test coverage of controller behavior
    Evidence: .sisyphus/evidence/task-6-all-tests-pass.log
  ```

  **Evidence to Capture**:
  - [ ] Full test output

  **Commit**: YES (groups with 7, 8)
  - Message: `test(webhook): add feature tests for TwilioTemplateWebhookController`
  - Files: `tests/Feature/Http/Controllers/Api/TwilioTemplateWebhookTest.php`
  - Pre-commit: `php artisan test --filter=TwilioTemplateWebhookTest`

- [ ] 7. Job unit tests

  **What to do**:
  - Create `tests/Unit/Jobs/ProcessTwilioTemplateWebhookTest.php`
  - Test: approved status → template updated + notification sent
  - Test: rejected status → template updated + rejection_reason set + notification sent
  - Test: unchanged status → no update, no notification
  - Test: template not found → graceful return, warning logged
  - Test: unknown status → graceful return, warning logged
  - Test: unique job — same contentSid prevents concurrent processing
  - Use factories for Gym, WhatsAppAccount, WhatsAppTemplate
  - Follow existing `ProcessMetaTemplateWebhookTest.php` patterns

  **Must NOT do**:
  - No feature tests (those are in Task 6)
  - No Twilio API mocking (job doesn't call Twilio API)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Unit tests require factory setup, queue testing, notification assertions
  - **Skills**: [`phpunit-best-practices`]
    - `phpunit-best-practices`: PHPUnit test structure, factories, notification testing

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T4)
  - **Parallel Group**: Wave 3 (with Tasks 6, 8, 9)
  - **Blocks**: FINAL wave
  - **Blocked By**: Task 4

  **References**:
  - `tests/Unit/Jobs/ProcessMetaTemplateWebhookTest.php` — Meta job test patterns to follow
  - `app/Jobs/ProcessTwilioTemplateWebhook.php` (Task 4 output) — Job to test
  - `app/Models/WhatsAppTemplate.php` — Model with factory
  - `app/Models/WhatsAppAccount.php` — Related model for test setup

  **Acceptance Criteria**:
  - [ ] All unit tests pass: `php artisan test --filter=ProcessTwilioTemplateWebhookTest`
  - [ ] Tests cover: approved, rejected, unchanged, not found, unknown status, unique job
  - [ ] Notification assertions verify correct notification class sent

  **QA Scenarios**:
  ```
  Scenario: All job unit tests pass
    Tool: Bash (php artisan test)
    Steps:
      1. Run: php artisan test --filter=ProcessTwilioTemplateWebhookTest
      2. Assert: All tests pass, 0 failures, 0 errors
    Expected Result: Complete test coverage of job behavior
    Evidence: .sisyphus/evidence/task-7-all-tests-pass.log
  ```

  **Evidence to Capture**:
  - [ ] Full test output

  **Commit**: YES (groups with 6, 8)
  - Message: `test(webhook): add unit tests for ProcessTwilioTemplateWebhook job`
  - Files: `tests/Unit/Jobs/ProcessTwilioTemplateWebhookTest.php`
  - Pre-commit: `php artisan test --filter=ProcessTwilioTemplateWebhookTest`

- [ ] 8. Status mapper unit tests

  **What to do**:
  - Create `tests/Unit/Services/Support/WhatsAppTemplateStatusMapperTest.php`
  - Test all Meta event mappings: APPROVED, REJECTED, PENDING, PENDING_DELETION, FLAGGED, PAUSED, DISABLED
  - Test all Twilio status mappings: approved, rejected, pending, disabled, paused
  - Test case-insensitivity: 'Approved', 'APPROVED', 'approved' all map to APPROVED
  - Test unknown status returns null
  - Test empty string returns null

  **Must NOT do**:
  - No database access
  - No provider-specific logic

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Pure function testing with data providers
  - **Skills**: [`phpunit-best-practices`]
    - `phpunit-best-practices`: Data providers, pure function testing

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T1)
  - **Parallel Group**: Wave 3 (with Tasks 6, 7, 9)
  - **Blocks**: FINAL wave
  - **Blocked By**: Task 1

  **References**:
  - `app/Services/Support/WhatsAppTemplateStatusMapper.php` (Task 1 output) — Mapper to test
  - `app/Enums/WhatsAppTemplateStatusEnum.php` — Expected enum values

  **Acceptance Criteria**:
  - [ ] All mapper tests pass: `php artisan test --filter=WhatsAppTemplateStatusMapperTest`
  - [ ] Data provider covers all status variants

  **QA Scenarios**:
  ```
  Scenario: All status mapper tests pass
    Tool: Bash (php artisan test)
    Steps:
      1. Run: php artisan test --filter=WhatsAppTemplateStatusMapperTest
      2. Assert: All tests pass with data provider covering 10+ status variants
    Expected Result: All status mappings verified
    Evidence: .sisyphus/evidence/task-8-all-tests-pass.log
  ```

  **Evidence to Capture**:
  - [ ] Full test output

  **Commit**: YES (groups with 6, 7)
  - Message: `test(webhook): add unit tests for WhatsAppTemplateStatusMapper`
  - Files: `tests/Unit/Services/Support/WhatsAppTemplateStatusMapperTest.php`
  - Pre-commit: `php artisan test --filter=WhatsAppTemplateStatusMapperTest`

- [ ] 9. Deprecate polling commands

  **What to do**:
  - Add `@deprecated` PHPDoc to `SyncWhatsAppTemplates` class: "Use TwilioTemplateWebhookController for real-time template status updates instead. This command will be removed in a future version."
  - Add `@deprecated` PHPDoc to `SyncWhatsAppTemplateStatusesCommand` class: same message
  - Add `$this->warn('DEPRECATED: ...')` at the start of each command's `handle()` method
  - Update command descriptions to include "[DEPRECATED]" prefix
  - Do NOT remove or disable the commands — they should still work for backward compatibility

  **Must NOT do**:
  - No deletion of commands
  - No removal of command registrations
  - No breaking changes to command signatures

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple annotation additions and warning messages
  - **Skills**: [`laravel-best-practices`]
    - `laravel-best-practices`: Laravel command conventions, deprecation patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES (independent of all other tasks)
  - **Parallel Group**: Wave 3 (with Tasks 6, 7, 8)
  - **Blocks**: FINAL wave
  - **Blocked By**: None (can start immediately, but scheduled for Wave 3)

  **References**:
  - `app/Console/Commands/SyncWhatsAppTemplates.php` — Command to deprecate
  - `app/Console/Commands/SyncWhatsAppTemplateStatusesCommand.php` — Command to deprecate

  **Acceptance Criteria**:
  - [ ] Both commands have `@deprecated` PHPDoc blocks
  - [ ] Both commands output deprecation warning when run
  - [ ] Both commands still function correctly (no breaking changes)
  - [ ] Command descriptions include "[DEPRECATED]" prefix

  **QA Scenarios**:
  ```
  Scenario: Deprecated commands still work but show warning
    Tool: Bash (php artisan)
    Steps:
      1. Run: php artisan app:sync-whats-app-templates
      2. Assert: Output contains "DEPRECATED" warning
      3. Assert: Command completes without error (exit code 0)
    Expected Result: Warning shown, command still functional
    Evidence: .sisyphus/evidence/task-9-deprecated-warning.log

  Scenario: Deprecated status sync command shows warning
    Tool: Bash (php artisan)
    Steps:
      1. Run: php artisan whatsapp:sync-template-statuses
      2. Assert: Output contains "DEPRECATED" warning
      3. Assert: Command completes
    Expected Result: Warning shown, command still functional
    Evidence: .sisyphus/evidence/task-9-deprecated-status-sync.log
  ```

  **Evidence to Capture**:
  - [ ] Command output showing deprecation warnings

  **Commit**: YES (groups with 6, 7, 8)
  - Message: `chore(webhook): deprecate polling commands in favor of real-time webhook`
  - Files: `app/Console/Commands/SyncWhatsAppTemplates.php`, `app/Console/Commands/SyncWhatsAppTemplateStatusesCommand.php`
  - Pre-commit: `php -l app/Console/Commands/SyncWhatsAppTemplates.php && php -l app/Console/Commands/SyncWhatsAppTemplateStatusesCommand.php`

---

## Final Verification Wave (MANDATORY — after ALL implementation tasks)

> 4 review agents run in PARALLEL. ALL must APPROVE. Present consolidated results to user and get explicit "okay" before completing.

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read file, curl endpoint, run command). For each "Must NOT Have": search codebase for forbidden patterns — reject with file:line if found. Check evidence files exist in .sisyphus/evidence/. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `tsc --noEmit` + linter + `php artisan test`. Review all changed files for: `as any`/`@ts-ignore`, empty catches, console.log in prod, commented-out code, unused imports. Check AI slop: excessive comments, over-abstraction, generic names (data/result/item/temp).
  Output: `Build [PASS/FAIL] | Lint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Start from clean state. Execute EVERY QA scenario from EVERY task — follow exact steps, capture evidence. Test cross-task integration (controller dispatching job, job updating template, notification sent). Test edge cases: invalid signature, unknown status, template not found. Save to `.sisyphus/evidence/final-qa/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | Edge Cases [N tested] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff (git log/diff). Verify 1:1 — everything in spec was built (no missing), nothing beyond spec was built (no creep). Check "Must NOT do" compliance. Detect cross-task contamination. Flag unaccounted changes.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Commit Strategy

- **Wave 1**: `feat(webhook): extract shared WhatsAppTemplateStatusMapper and refactor Meta job` — `app/Services/Support/WhatsAppTemplateStatusMapper.php`, `app/Jobs/ProcessMetaTemplateWebhook.php`, `tests/Unit/Services/Support/WhatsAppTemplateStatusMapperTest.php` — `php artisan test --filter=WhatsAppTemplateStatusMapper`
- **Wave 2**: `feat(webhook): add Twilio template webhook controller, job, and routes` — `app/Http/Controllers/Api/TwilioTemplateWebhookController.php`, `app/Jobs/ProcessTwilioTemplateWebhook.php`, `routes/api.php`, `config/services.php` — `php artisan route:list --path=webhooks/twilio/templates`
- **Wave 3**: `test(webhook): add comprehensive tests and deprecate polling commands` — `tests/Feature/Http/Controllers/Api/TwilioTemplateWebhookTest.php`, `tests/Unit/Jobs/ProcessTwilioTemplateWebhookTest.php`, `tests/Unit/Services/Support/WhatsAppTemplateStatusMapperTest.php`, `app/Console/Commands/SyncWhatsAppTemplates.php`, `app/Console/Commands/SyncWhatsAppTemplateStatusesCommand.php` — `php artisan test --filter=TwilioTemplate`
- **Final**: No commit — present verification results to user

---

## Success Criteria

### Verification Commands
```bash
php artisan test --filter=TwilioTemplate  # Expected: all tests pass, 0 failures
php artisan test --filter=WhatsAppTemplateStatusMapper  # Expected: all status mappings pass
php artisan route:list --path=webhooks/twilio/templates  # Expected: GET and POST routes listed
vendor/bin/pint --dirty  # Expected: no changes needed
```

### Final Checklist
- [ ] Twilio webhook endpoint responds correctly to valid/invalid requests
- [ ] ProcessTwilioTemplateWebhook job updates templates and sends notifications
- [ ] WhatsAppTemplateStatusMapper handles all Meta and Twilio status values
- [ ] ProcessMetaTemplateWebhook uses shared mapper (no duplicate mapStatus)
- [ ] All tests pass (controller, job, mapper)
- [ ] Polling commands show deprecation warnings but still work
- [ ] No raw errors exposed in webhook responses
- [ ] No env() calls outside config files
- [ ] All new files have declare(strict_types=1)
- [ ] Pint formatting clean on all files
