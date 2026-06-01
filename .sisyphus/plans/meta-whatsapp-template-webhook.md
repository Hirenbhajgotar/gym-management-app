# Meta WhatsApp Template Status Webhook

## TL;DR

> **Quick Summary**: Build a real-time webhook endpoint for Meta's WhatsApp Business API that receives `message_template_status_update` events — replacing the current polling-only approach (`SyncWhatsAppTemplateStatusesCommand` + `MetaTemplateService::syncTemplateStatus()`). When Meta approves, rejects, or changes a template's status, the webhook updates the database instantly with cache invalidation.

> **Deliverables**:
> - `WhatsAppTemplateWebhookController` — GET (verification) + POST (webhook handler)
> - `ProcessMetaTemplateWebhook` Job — queued, locked, atomic status updates
> - Routes: `GET/POST /api/webhooks/whatsapp/templates`
> - Full feature + unit tests

> **Estimated Effort**: Medium (~3-4 waves)
> **Parallel Execution**: YES - 2 waves + final
> **Critical Path**: T1 → T2 → T3 → T4 → T5 → F1-F4

---

## Context

### Original Request
Add a Meta WhatsApp webhook handler that receives template status updates (APPROVED, REJECTED, etc.) from Meta's WhatsApp Business API via `message_template_status_update` field events, updating the database in real-time instead of relying only on polling.

### Interview Summary
**Key Findings from Codebase Exploration**:
- **Current polling exists**: `MetaTemplateService::syncTemplateStatus()` polls Meta Graph API per-template. `SyncWhatsAppTemplateStatusesCommand` iterates all templates.
- **Existing webhook patterns**: Facebook/Instagram lead controllers use `X-Hub-Signature-256` verification + `hub_mode`/`hub_verify_token`/`hub_challenge` for setup. Routes are under `/api/webhooks/*` with NO auth middleware.
- **Cache patterns**: `Cache::flexible()` for template listings (300s fresh, 3600s stale). `Cache::lock("meta_template_sync:{$id}", 30)` for sync concurrency.
- **DTOs already exist**: `TemplateSubmissionResult`, `TemplateCollectionResult`, `CreateTemplateData`, etc. in `app/DTOs/WhatsAppTemplates/`.
- **`WhatsAppTemplate` model**: Has `meta_template_id`, `status` (WhatsAppTemplateStatusEnum), `rejection_reason`, `gym_id`.
- **`MetaTemplateService::mapStatus()`**: Shared status mapping logic (PENDING/APPROVED/REJECTED/DISABLED → WhatsAppTemplateStatusEnum).
- **`WhatsAppLeadController` is empty** — only constructor with processor injection. No conflict with new controller.

### Metis Review
**Metis consultation was attempted but timed out. Self-gap analysis performed:**
- Architecture approach validated against existing patterns (FacebookLeadController, InstagramLeadController)
- Signature verification: Meta uses the same `X-Hub-Signature-256` pattern as Facebook — confirmed via `WhatsAppLeadProcessor::verifyWebhook()` at line 91
- Queue job pattern aligns with existing Laravel conventions in the project
- No schema changes needed — all data exists on `WhatsAppTemplate` model
- Key question: whether to reuse `VerifySocialMediaWebhook` middleware or handle verification in-controller. Decision: handle in-controller (follows FacebookLeadController pattern, avoids false-positive route matching since path contains "whatsapp" which would confuse the middleware)

---

## Work Objectives

### Core Objective
Handle incoming Meta WhatsApp Business API webhook notifications for `message_template_status_update` events, updating template statuses in real-time with proper locking, cache invalidation, and error handling.

### Concrete Deliverables
- `app/Http/Controllers/Api/WhatsAppTemplateWebhookController.php` — GET (verification) + POST (handler)
- `app/Jobs/ProcessMetaTemplateWebhook.php` — Queued job for async processing
- Route addition in `routes/api.php`: `GET /api/webhooks/whatsapp/templates` + `POST /api/webhooks/whatsapp/templates`
- `tests/Feature/Http/Controllers/Api/WhatsAppTemplateWebhookTest.php`
- `tests/Unit/Jobs/ProcessMetaTemplateWebhookTest.php`

### Definition of Done
- [ ] `php artisan test --filter=WhatsAppTemplateWebhook` → PASS (all tests, 0 failures)
- [ ] `vendor/bin/pint --dirty --format agent` → PASS
- [ ] `php -l` on all new files → No syntax errors
- [ ] Meta webhook verification (GET with valid `hub_verify_token`) returns `$hubChallenge` as plain text 200
- [ ] Meta webhook POST with valid `X-Hub-Signature-256` dispatches the job
- [ ] Meta webhook POST with invalid signature returns 401
- [ ] Job updates template status in DB and busts cache

### Must Have
- Webhook verification endpoint (GET) per Meta's Facebook-style `hub_mode`/`hub_verify_token`/`hub_challenge`
- Webhook POST handler with `X-Hub-Signature-256` verification
- Queued job (`ProcessMetaTemplateWebhook`) with `Cache::lock()` to prevent concurrent updates
- Status mapping from Meta event values → `WhatsAppTemplateStatusEnum`
- Cache invalidation for `meta_templates:gym:*` keys on status change
- Comprehensive test coverage

### Must NOT Have (Guardrails)
- **No schema changes** — all required columns (`meta_template_id`, `status`, `rejection_reason`) already exist on `WhatsAppTemplate`
- **No changes to existing `SyncWhatsAppTemplateStatusesCommand`** — it can remain as a fallback; this webhook reduces its importance but doesn't remove the need
- **No changes to `MetaTemplateService::syncTemplateStatus()`** — it can remain as a polling fallback; the webhook is additive
- **No changes to Twilio webhooks or lead processing** — completely separate concern
- **No middleware wrapping** — route stays unauthenticated like all other webhook routes; signature verification happens in-controller (follows existing pattern)

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed. No exceptions.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit configured, tests in `tests/Feature/` and `tests/Unit/`)
- **Automated tests**: YES (tests-after — write tests after implementation)
- **Framework**: PHPUnit (project convention)
- **Pattern**: Feature test for the controller, Unit test for the job

### QA Policy
Every task MUST include agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **API/Backend**: Use Bash (curl) to send HTTP requests and assert status codes + response bodies
- **Library/Module**: Use Bash (`php artisan tinker`) to import and call methods, verify behavior
- **Database**: Use `php artisan tinker` to query model and assert state changes

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Start Immediately — 3 parallel tasks):
├── Task 1: Controller — GET verification + POST handler with signature check [quick]
├── Task 2: Job — ProcessMetaTemplateWebhook [quick]
├── Task 3: Routes — register GET + POST routes [quick]

Wave 2 (After Wave 1 — 2 parallel tasks):
├── Task 4: Feature test — controller verification + webhook handling [phpunit-best-practices]
├── Task 5: Unit test — job processing [phpunit-best-practices]

Wave FINAL (After ALL tasks — 4 parallel reviews):
├── Task F1: Plan compliance audit (oracle)
├── Task F2: Code quality review (unspecified-high)
├── Task F3: Real manual QA (unspecified-high)
├── Task F4: Scope fidelity check (deep)
```

### Dependency Matrix
- **T1**: None → T3, T4
- **T2**: None → T4, T5
- **T3**: T1 → F1-F4
- **T4**: T1, T2, T3 → F1-F4
- **T5**: T2 → F1-F4

### Agent Dispatch Summary
- **Wave 1**: 3 agents (T1-T3) — all `quick`
- **Wave 2**: 2 agents (T4-T5) — both `phpunit-best-practices`
- **Final**: 4 agents (F1-F4) — oracle, unspecified-high (×2), deep

---

## TODOs

- [ ] 1. **WhatsAppTemplateWebhookController** — GET verification + POST webhook handler

  **What to do**:
  Create `app/Http/Controllers/Api/WhatsAppTemplateWebhookController.php` following the `FacebookLeadController` pattern. The controller handles two responsibilities:
  
  **GET endpoint (`verifyMetaWebhook`)**:
  - Read `hub_mode`, `hub_verify_token`, `hub_challenge` from query params
  - Compare `hub_verify_token` against `config('services.whatsapp.webhook_verify_token')`
  - If `$hubMode === 'subscribe' && $hubVerifyToken === $verifyToken`, return `response($hubChallenge, 200)` with header `Content-Type: text/plain`
  - Otherwise return `response()->json(['error' => 'Verification failed'], 403)`
  - Log all verification attempts (success and failure)
  
  **POST endpoint (`handleTemplateStatusWebhook`)**:
  - Log incoming webhook request (headers, payload, IP) — follow pattern from `FacebookLeadController::webhook()` line 25-30
  - **Signature verification**: Verify `X-Hub-Signature-256` header
    - Get signature from `$request->header('X-Hub-Signature-256')` (format: `sha256=<hex>`)
    - Compute HMAC-SHA256 of `$request->getContent()` using `config('services.facebook.app_secret')` (Meta uses same app_secret for X-Hub-Signature-256 as Facebook)
    - Compare using `hash_equals()` to prevent timing attacks
    - If mismatch: log warning, return 401
  - If signature valid:
    - Parse payload: iterate `$data['entry']`, iterate `$entry['changes']`, find changes where `$change['field'] === 'message_template_status_update'`
    - For each matching change, extract: `message_template_id` (string), `event` (string: APPROVED/REJECTED/PENDING/etc.), `reason` (string, nullable), `previous_event` (string, nullable)
    - Dispatch `ProcessMetaTemplateWebhook` job with the extracted data
  - Return `response()->json(['status' => 'success'], 200)` after dispatching
  - On exception: log full error, return 500

  **Must NOT do**:
  - Do NOT modify `VerifySocialMediaWebhook` middleware — verification is handled in-controller
  - Do NOT modify `WhatsAppLeadController` — this is a new, separate controller
  - Do NOT add auth middleware to the route
  - Do NOT process synchronously — always dispatch the job

  **Recommended Agent Profile**:
  - **Category**: `quick` — Single controller file, well-defined patterns, clear method signatures
  - **Skills**: []
  - **Reasoning**: Straightforward controller with two methods. Both patterns exist already in the codebase (FacebookLeadController for verification, InstagramLeadController for signature check). No complex business logic.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3)
  - **Blocks**: Task 3 (routes)
  - **Blocked By**: None

  **References**:
  - `app/Http/Controllers/Api/FacebookLeadController.php` — Primary pattern to follow: webhook() handles GET verification + POST processing, verifyWebhook() private method for hub flow, X-Hub-Signature-256 validation
  - `app/Http/Controllers/Api/InstagramLeadController.php` — verifyWebhook() GET handler at lines 88-132, handleWebhook() POST handler pattern, signature verification at line 43
  - `app/Services/WhatsAppLeadProcessor.php:91` — `X-Hub-Signature-256` verification implementation using `hash_equals()` and HMAC-SHA256
  - `config/services.php` lines 54-64 — `whatsapp.webhook_verify_token` and `whatsapp.access_token` config keys

  **Acceptance Criteria**:

  **QA Scenarios**:

  ```
  Scenario: GET webhook verification with valid token
    Tool: Bash (curl)
    Preconditions: config('services.whatsapp.webhook_verify_token') is set to 'test_verify_token'
    Steps:
      1. Start Laravel dev server: `php artisan serve &`
      2. curl -v "http://localhost:8000/api/webhooks/whatsapp/templates?hub_mode=subscribe&hub_verify_token=test_verify_token&hub_challenge=12345"
    Expected Result: HTTP 200, body = "12345", Content-Type: text/plain
    Evidence: .sisyphus/evidence/task-1-verify-valid-token.txt

  Scenario: GET webhook verification with invalid token
    Tool: Bash (curl)
    Preconditions: Same
    Steps:
      1. curl -v "http://localhost:8000/api/webhooks/whatsapp/templates?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=12345"
    Expected Result: HTTP 403, body = {"error":"Verification failed"}
    Evidence: .sisyphus/evidence/task-1-verify-invalid-token.txt

  Scenario: POST webhook with valid signature
    Tool: Bash (curl)
    Preconditions: APP_SECRET set in config, test payload available
    Steps:
      1. Generate valid signature: `echo -n '{"entry":[{"changes":[{"field":"message_template_status_update","value":{"event":"APPROVED","message_template_id":"123","message_template_name":"test"}}]}]}' | openssl dgst -sha256 -hmac "$APP_SECRET"`
      2. curl -X POST "http://localhost:8000/api/webhooks/whatsapp/templates" -H "Content-Type: application/json" -H "X-Hub-Signature-256: sha256=<computed>" -d '{"entry":[{"changes":[{"field":"message_template_status_update","value":{"event":"APPROVED","message_template_id":"123","message_template_name":"test"}}]}]}'
    Expected Result: HTTP 200, body = {"status":"success"}
    Evidence: .sisyphus/evidence/task-1-valid-signature.txt

  Scenario: POST webhook with invalid signature
    Tool: Bash (curl)
    Steps:
      1. curl -X POST "http://localhost:8000/api/webhooks/whatsapp/templates" -H "X-Hub-Signature-256: sha256=invalid" -d '{"test":true}'
    Expected Result: HTTP 401, body = {"error":"Invalid signature"}
    Evidence: .sisyphus/evidence/task-1-invalid-signature.txt
  ```

  **Evidence to Capture**:
  - [ ] HTTP status codes, response bodies, Content-Type headers for all scenarios
  - [ ] Laravel log entries showing verification attempts

  **Commit**: YES (groups with task 3)
  - Message: `feat(whatsapp): add template status webhook controller`
  - Files: `app/Http/Controllers/Api/WhatsAppTemplateWebhookController.php`
  - Pre-commit: `php artisan test --filter=WhatsAppTemplateWebhook` (select feature tests first)

---

- [ ] 2. **ProcessMetaTemplateWebhook Job** — Queued job for async template status updates

  **What to do**:
  Create `app/Jobs/ProcessMetaTemplateWebhook.php` — a queued job that processes a single Meta template status update event.

  **Properties**:
  - `public string $metaTemplateId` — Meta's template ID
  - `public string $event` — The event value (APPROVED, REJECTED, etc.)
  - `public ?string $reason` — Rejection reason (nullable)
  - `public ?string $previousEvent` — Previous status (nullable)

  **handle() method**:
  - **Cache::lock()**: Acquire `Cache::lock("meta_template_webhook:{$this->metaTemplateId}", 30)`. If lock fails, release and return (prevents concurrent processing of same template)
  - **Find template**: `WhatsAppTemplate::where('meta_template_id', $this->metaTemplateId)->first()`
    - If not found: log warning, return (template might be managed by a different system/sync)
  - **Map status**: Use same mapping logic as `MetaTemplateService::mapStatus()` — map Meta events to `WhatsAppTemplateStatusEnum`:
    - `APPROVED` → `WhatsAppTemplateStatusEnum::APPROVED`
    - `REJECTED` → `WhatsAppTemplateStatusEnum::REJECTED`
    - `PENDING` → `WhatsAppTemplateStatusEnum::PENDING`
    - `PENDING_DELETION` → `WhatsAppTemplateStatusEnum::PENDING` (still pending until deleted)
    - `FLAGGED` → `WhatsAppTemplateStatusEnum::REJECTED` (flagged = rejected for policy violation)
    - `PAUSED` → `WhatsAppTemplateStatusEnum::DISABLED`
    - `DISABLED` → `WhatsAppTemplateStatusEnum::DISABLED`
    - Default → log unknown event, return without changes
  - **Skip if same status**: If `$template->status->value === $newStatus->value`, log and return (no-op)
  - **Update template**: 
    - `$template->status = $mappedStatus`
    - If `$this->event === 'REJECTED' || $this->event === 'FLAGGED'`: `$template->rejection_reason = $this->reason`
    - `$template->save()`
  - **Bust cache**: `Cache::forget('meta_templates:gym:' . $template->gym_id . ':waba:*')` — use wildcard-style forget or clear the specific gym's template cache
    - Actually use `Cache::forget()` with the exact cache key pattern: iterate gym's WABA IDs and forget each key, OR better: store the cache key pattern and forget with `Cache::forget()` using the same key generation as `MetaTemplateService::templatesCacheKey()`. Since we don't have the waba_id here, we can use a broader flush approach or simply set a flag that the next `getTemplates()` call will bypass cache.
    - Practical approach: `Cache::put("meta_templates:gym:{$template->gym_id}:stale", true, 60)` — this acts as a stale signal. But simpler: just forget the exact possible cache keys. Since `MetaTemplateService::templatesCacheKey()` uses `meta_templates:gym:{gymId}:waba:{wabaId}`, and we don't have wabaId, the safest approach is to use `Cache::forget("meta_templates:gym:{$template->gym_id}:*")` — but Cache::forget doesn't support wildcards. Instead, use `Cache::put("meta_templates:gym:{$template->gym_id}:stale_flag", true, 60)` as a signal flag, or better, just clear the gym's template cache by iterating known keys.
    - **Simplest correct approach**: Set a time-based flag: `Cache::put("meta_templates:gym:{$template->gym_id}:webhook_updated_at", now()->timestamp, 600)` — then in `MetaTemplateService::getTemplates()`, add a check before returning cached data. However, this requires modifying MetaTemplateService.
    - **Alternative**: Notify the cache layer indirectly. Since `getTemplates()` uses `Cache::flexible()` with 300s TTL, the cached data will be stale for at most 5 minutes. This is acceptable since the webhook updates the DB directly. But better: bump a version counter: `Cache::increment("meta_templates:gym:{$template->gym_id}:version")`. Then in the templates cache key, append the version: `$this->templatesCacheKey() . ':v' . Cache::get("meta_templates:gym:{$gymId}:version", 1)`.
    - **Recommended**: Simple approach for now — just `Cache::forget("meta_templates:gym:{$template->gym_id}:*")` doesn't work, so use a cache version key approach:
      ```php
      // In ProcessMetaTemplateWebhook::handle():
      $versionKey = "meta_templates_cache_version:{$template->gym_id}";
      Cache::increment($versionKey);
      ```
      Then in MetaTemplateService, template cache key would include this version. BUT we decided not to modify MetaTemplateService.
      
    - **Simplest effective approach**: Just forget the gym-level cache prefix. Since `Cache::flexible()` stores under the exact key from `templatesCacheKey()`, and we don't have the wabaId, the most practical approach is to set a staleness flag that getTemplates checks, OR accept the 5-min staleness (acceptable for template status).
    
    - **RECOMMENDED**: Forget using the prefix. Since `Cache::forget` doesn't support wildcards and we can't know the wabaId, just set a short TTL stale flag:
      ```php
      Cache::put("meta_template_webhook_updated:{$template->gym_id}", true, 60);
      ```
      And add a check in `MetaTemplateService::getTemplates()` before returning cached data:
      ```php
      if (Cache::get("meta_template_webhook_updated:{$gymId}")) {
          Cache::forget($this->templatesCacheKey());
          Cache::forget("meta_template_webhook_updated:{$gymId}");
      }
      ```
      This is a minimal 3-line change to `MetaTemplateService.php` that doesn't alter existing logic.
  
  - **Log success**: Log what changed (template ID, from status, to status)

  **retryUntil()**: 3 attempts, 30s backoff (standard pattern)
  **failed()**: Log error with full context

  **Must NOT do**:
  - Do NOT process inline/un-queued — always dispatch async
  - Do NOT throw exceptions on expected errors (template not found, lock not acquired)
  - Do NOT modify `MetaTemplateService::syncTemplateStatus()` — this is additive

  **Recommended Agent Profile**:
  - **Category**: `quick` — Single job file, well-defined business logic, follows Laravel patterns
  - **Skills**: []
  - **Reasoning**: A single-file job with clear responsibilities: receive webhook data, lock, find, map, update, bust cache. The `mapStatus` logic already exists in MetaTemplateService — just extract and reuse pattern.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3)
  - **Blocks**: Task 5 (unit test)
  - **Blocked By**: None

  **References**:
  - `app/Services/Meta/MetaTemplateService.php:132-172` — `syncTemplateStatus()` method: Cache::lock pattern, mapStatus logic, template save pattern. THIS IS THE PRIMARY REFERENCE for the job's core logic
  - `app/Services/Meta/MetaTemplateService.php:206-215` — `mapStatus()` — either call this method directly (if made public/static) or replicate the pattern in the job
  - `app/Models/WhatsAppTemplate.php` — Model with `meta_template_id`, `status`, `rejection_reason`, `gym_id`, helper methods like `markAsApproved()` and `markAsRejected()` at lines 203-216
  - `app/Enums/WhatsAppTemplateStatusEnum.php` — All 6 status cases: DRAFT, PENDING, APPROVED, REJECTED, DISABLED, UNSUBMITTED
  - Existing job example (any in `app/Jobs/`): Follow project's queued job conventions (trait imports, retry config, failed method)

  **Acceptance Criteria**:

  **QA Scenarios**:

  ```
  Scenario: Job processes APPROVED event
    Tool: Bash (php artisan tinker)
    Preconditions: Create a WhatsAppTemplate record with meta_template_id='test_mti_1', status=PENDING, rejection_reason=null via tinker
    Steps:
      1. Use tinker to create the template: `$t = \App\Models\WhatsAppTemplate::factory()->create(['meta_template_id' => 'test_mti_1', 'status' => \App\Enums\WhatsAppTemplateStatusEnum::PENDING]);`
      2. Dispatch the job: `\App\Jobs\ProcessMetaTemplateWebhook::dispatch('test_mti_1', 'APPROVED', null, 'PENDING');`
      3. Run queue worker: `php artisan queue:work --once`
      4. Refresh template: `$t->refresh(); echo $t->status->value;`
    Expected Result: Template status = "approved", rejection_reason = null
    Evidence: .sisyphus/evidence/task-2-job-approved.txt

  Scenario: Job processes REJECTED event
    Tool: Bash (php artisan tinker)
    Preconditions: Create template with meta_template_id='test_mti_2', status=PENDING
    Steps:
      1. Create template
      2. Dispatch: `ProcessMetaTemplateWebhook::dispatch('test_mti_2', 'REJECTED', 'Violated policy X', 'PENDING');`
      3. Run queue worker
      4. Refresh template
    Expected Result: Template status = "rejected", rejection_reason = "Violated policy X"
    Evidence: .sisyphus/evidence/task-2-job-rejected.txt

  Scenario: Job skips when template not found
    Tool: Bash (php artisan tinker)
    Steps:
      1. Dispatch: `ProcessMetaTemplateWebhook::dispatch('nonexistent_id', 'APPROVED', null, 'PENDING');`
      2. Run queue worker
    Expected Result: Job completes without error (gracefully handles not-found). No exception thrown.
    Evidence: .sisyphus/evidence/task-2-job-not-found.txt

  Scenario: Job uses Cache::lock to prevent duplicates
    Tool: Bash (php artisan tinker)
    Steps:
      1. Acquire lock manually: `$lock = \Illuminate\Support\Facades\Cache::lock("meta_template_webhook:test_mti_3", 30); $lock->get();`
      2. Dispatch: `ProcessMetaTemplateWebhook::dispatch('test_mti_3', 'APPROVED', null, 'PENDING');`
      3. Run queue worker
      4. Release: `$lock->release();`
    Expected Result: Job completes without error (skips processing). No exception. Template unchanged.
    Evidence: .sisyphus/evidence/task-2-job-lock.txt
  ```

  **Evidence to Capture**:
  - [ ] Template status changes confirmed via tinker queries
  - [ ] Log entries showing job processing
  - [ ] Lock behavior verified (concurrent executions skipped)

  **Commit**: YES (groups with tasks 4, 5)
  - Message: `feat(whatsapp): add ProcessMetaTemplateWebhook job`
  - Files: `app/Jobs/ProcessMetaTemplateWebhook.php`
  - Pre-commit: `php artisan test --filter=ProcessMetaTemplateWebhook`

---

- [ ] 3. **Register webhook routes** — Add GET and POST routes for template status webhook

  **What to do**:
  Add two routes to `routes/api.php` inside the existing `Route::prefix('webhooks')` group (around line 35).

  **Route definitions**:
  ```php
  // WhatsApp Template Status Webhook
  Route::get('/whatsapp/templates', [WhatsAppTemplateWebhookController::class, 'verifyMetaWebhook'])
      ->name('api.webhooks.whatsapp.templates.verify');

  Route::post('/whatsapp/templates', [WhatsAppTemplateWebhookController::class, 'handleTemplateStatusWebhook'])
      ->name('api.webhooks.whatsapp.templates.status');
  ```

  Add the import at top of file (around line 12):
  ```php
  use App\Http\Controllers\Api\WhatsAppTemplateWebhookController;
  ```

  **Placement**: Add right after the existing WhatsApp webhook routes (after line 47, before the IndiaMart commented routes).

  **Must NOT do**:
  - Do NOT wrap with auth sanctum middleware — webhook routes are public (same as all other webhook routes)
  - Do NOT remove or modify existing routes
  - Do NOT use `middleware('api')` explicitly — the entire file is already under API middleware group

  **Recommended Agent Profile**:
  - **Category**: `quick` — 5-line route addition, trivial task
  - **Skills**: []
  - **Reasoning**: Adding 2 routes to an existing group, following identical patterns. No logic.

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 1 - controller must exist)
  - **Parallel Group**: Sequential after Task 1
  - **Blocks**: F1-F4
  - **Blocked By**: Task 1

  **References**:
  - `routes/api.php` lines 34-94 — Full webhook route group showing existing patterns (Facebook verify/handle, Instagram verify/handle, WhatsApp verify/handle)
  - `routes/api.php` lines 1-18 — Import section showing how other webhook controllers are imported

  **Acceptance Criteria**:

  **QA Scenarios**:

  ```
  Scenario: GET route responds to correct path
    Tool: Bash (curl)
    Steps:
      1. php artisan serve &
      2. curl -o /dev/null -w "%{http_code}" "http://localhost:8000/api/webhooks/whatsapp/templates?hub_mode=subscribe&hub_verify_token=test&hub_challenge=1"
    Expected Result: HTTP 403 (verification fails since no valid token set — that's OK, proves route is active and calls the controller)
    
    Alternative using route check:
      1. php artisan route:list --path=api/webhooks/whatsapp/templates
    Expected Result: Shows GET and POST routes registered
    Evidence: .sisyphus/evidence/task-3-routes-registered.txt

  Scenario: POST route accepts requests
    Tool: Bash (curl)
    Steps:
      1. curl -X POST "http://localhost:8000/api/webhooks/whatsapp/templates" -H "X-Hub-Signature-256: sha256=test" -d '{}'
    Expected Result: HTTP 401 (signature verification fails — proves route calls controller)
    Evidence: .sisyphus/evidence/task-3-post-route.txt
  ```

  **Evidence to Capture**:
  - [ ] Route list showing both routes registered
  - [ ] Both GET and POST routes route to the controller (verified by non-404 responses)

  **Commit**: YES (groups with task 1)
  - Message: `feat(whatsapp): add template status webhook controller`
  - Files: `routes/api.php`
  - Pre-commit: `php artisan route:list --path=api/webhooks/whatsapp/templates`

---

- [ ] 4. **Feature test: controller verification and webhook handling**

  **What to do**:
  Create `tests/Feature/Http/Controllers/Api/WhatsAppTemplateWebhookTest.php` with comprehensive PHPUnit feature tests.

  **Test the GET verification endpoint**:
  - `test_get_verify_returns_challenge_with_valid_token()`: Set config 'services.whatsapp.webhook_verify_token' to 'valid_token'. Send GET with hub_mode=subscribe, hub_verify_token=valid_token, hub_challenge=54321. Assert 200, body = "54321", header Content-Type = "text/plain".
  - `test_get_verify_returns_403_with_invalid_token()`: Same setup but with wrong hub_verify_token. Assert 403 JSON error.
  - `test_get_verify_returns_403_without_params()`: Send GET with no query params. Assert 403.

  **Test the POST webhook endpoint**:
  - `test_post_webhook_returns_200_with_valid_signature()`: 
    - Generate a valid HMAC-SHA256 signature for a test payload body
    - Mock/fake `Http` or just compute the HMAC in test and set header
    - Setup: Create a WhatsAppTemplate with meta_template_id that matches the payload
    - Use `Queue::fake()` and assert `ProcessMetaTemplateWebhook` was dispatched
    - Assert 200 response
  - `test_post_webhook_returns_401_with_invalid_signature()`: Send POST with bad `X-Hub-Signature-256`. Assert 401.
  - `test_post_webhook_returns_401_without_signature_header()`: Send POST without the signature header. Assert 401.
  - `test_post_webhook_dispatches_job_for_each_template()`: Payload with 2 template status changes. Assert 2 jobs dispatched.
  - `test_post_webhook_accepts_payload_with_multiple_entries()`: Payload with multiple `entry` items, each with changes. Assert jobs dispatched per change.

  **Setup**:
  - Use `RefreshDatabase` trait
  - Override config in `setUp()`: `config(['services.whatsapp.webhook_verify_token' => 'test_verify_token'])`
  - Override config in `setUp()`: `config(['services.facebook.app_secret' => 'test_app_secret'])` (Meta shares Facebook's app_secret for X-Hub-Signature-256)
  - Create a test payload that represents a real Meta webhook (use the exact structure from Meta docs)

  **Signature helper** for tests:
  ```php
  private function computeSignature(string $payload, string $secret): string
  {
      return 'sha256=' . hash_hmac('sha256', $payload, $secret);
  }
  ```

  **Must NOT do**:
  - Do NOT make real HTTP requests to external services
  - Do NOT use Pest syntax (project uses PHPUnit style)
  - Do NOT test the job logic in feature tests — just assert dispatch

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`phpunit-best-practices`]
  - **Required skill**: `phpunit-best-practices` — Test  structure, config overriding, Queue fake, data provider patterns

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on controller existence)
  - **Parallel Group**: Wave 2 (after Tasks 1, 2, 3)
  - **Blocks**: F1-F4
  - **Blocked By**: Tasks 1, 3

  **References**:
  - `tests/Feature/WhatsAppTemplateServiceTest.php` — Follow existing PHPUnit test conventions in the project (RefreshDatabase usage, factory patterns, config mocking)
  - `app/Http/Controllers/Api/WhatsAppTemplateWebhookController.php` — The controller being tested. The test must mirror the controller's logic.
  - PHPUnit docs for Laravel Queues: `Queue::fake()`, `Queue::assertPushed()`

  **Acceptance Criteria**:

  **QA Scenarios**:

  ```
  Scenario: All feature tests pass
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=WhatsAppTemplateWebhookTest --compact
    Expected Result: All tests pass (OK, 0 failures)
    Evidence: .sisyphus/evidence/task-4-feature-tests-pass.txt

  Scenario: Verification test passes
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=test_get_verify_returns_challenge_with_valid_token --compact
    Expected Result: Test passes
    Evidence: .sisyphus/evidence/task-4-test-verify-valid.txt

  Scenario: Signature validation test passes
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=test_post_webhook_returns_401_with_invalid_signature --compact
    Expected Result: Test passes
    Evidence: .sisyphus/evidence/task-4-test-invalid-sig.txt
  ```

  **Evidence to Capture**:
  - [ ] Full test run output showing all passing
  - [ ] Individual test outputs for verification and signature tests

  **Commit**: YES (groups with task 5)
  - Message: `test(whatsapp): add features tests for template webhook`
  - Files: `tests/Feature/Http/Controllers/Api/WhatsAppTemplateWebhookTest.php`

---

- [ ] 5. **Unit test: ProcessMetaTemplateWebhook job**

  **What to do**:
  Create `tests/Unit/Jobs/ProcessMetaTemplateWebhookTest.php` with comprehensive PHPUnit unit tests.

  **Tests required**:
  - `test_updates_template_to_approved()`: Create a template with status=PENDING. Call `$job->handle()` directly (use `dispatchNow()`). Assert template status = APPROVED.
  - `test_updates_template_to_rejected_with_reason()`: Same but with REJECTED event + reason. Assert status=REJECTED and rejection_reason set.
  - `test_skips_processing_when_lock_is_held()`: Acquire Cache::lock() before dispatching. Assert template status unchanged.
  - `test_skips_processing_for_unknown_template()`: Call job with non-existent meta_template_id. Assert no exception thrown.
  - `test_skips_processing_for_unmapped_event()`: Call job with unknown event. Assert template status unchanged.
  - `test_skips_processing_when_status_is_same()`: Call job with APPROVED on an already-approved template. Assert no DB update needed (check `updated_at`).
  - `test_flags_template_on_pending_deletion()`: Call job with PENDING_DELETION. Assert status = PENDING (or appropriate mapping).
  - `test_disabled_remaining_mappings()`: Test PAUSED → DISABLED, FLAGGED → REJECTED + reason set.

  **Setup**:
  - Use `RefreshDatabase`
  - Create WhatsAppTemplate via factory with specific meta_template_id
  - Use `Bus::fake()` or call `$job->handle()` directly
  - Override configs as needed

  **Must NOT do**:
  - Do NOT test the controller dispatch — that's the feature test's job
  - Do NOT test queue infrastructure (retries, failures) — test job LOGIC only

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`phpunit-best-practices`]
  - **Required skill**: `phpunit-best-practices` — Mocking, data providers, job testing patterns

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on job class)
  - **Parallel Group**: Wave 2 (after Task 2)
  - **Blocks**: F1-F4
  - **Blocked By**: Task 2

  **References**:
  - `app/Jobs/ProcessMetaTemplateWebhook.php` — The job being tested. Mirror its if-logic in test assertions.
  - `app/Models/WhatsAppTemplate.php` — Model with helper methods like `markAsApproved()`, `isApproved()`, etc.
  - `database/factories/WhatsAppTemplateFactory.php` — Factory for creating test records with known states

  **Acceptance Criteria**:

  **QA Scenarios**:

  ```
  Scenario: All unit tests pass
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=ProcessMetaTemplateWebhookTest --compact
    Expected Result: All tests pass (OK, 0 failures)
    Evidence: .sisyphus/evidence/task-5-unit-tests-pass.txt

  Scenario: Job correctly updates status
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=test_updates_template_to_approved --compact
    Expected Result: Test passes
    Evidence: .sisyphus/evidence/task-5-test-job-approved.txt
  ```

  **Evidence to Capture**:
  - [ ] Full test run output showing all passing
  - [ ] Individual test for status change

  **Commit**: YES (groups with task 4)
  - Message: `test(whatsapp): add unit tests for ProcessMetaTemplateWebhook`
  - Files: `tests/Unit/Jobs/ProcessMetaTemplateWebhookTest.php`

---

- [ ] 4. **Feature test: controller verification + webhook handling**

  **What to do**:
  Create `tests/Feature/Http/Controllers/Api/WhatsAppTemplateWebhookTest.php` — comprehensive feature tests for the controller.

  **Test file structure**:
  ```php
  <?php

  namespace Tests\Feature\Http\Controllers\Api;

  use App\Models\WhatsAppTemplate;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Illuminate\Support\Facades\Cache;
  use Illuminate\Support\Facades\Config;
  use Illuminate\Support\Facades\Log;
  use Illuminate\Support\Facades\Queue;
  use Tests\TestCase;
  ```

  **Test cases**:

  1. **test_verify_webhook_valid_token_returns_challenge()**
     - Set `Config::set('services.whatsapp.webhook_verify_token', 'valid_token')`
     - GET `/api/webhooks/whatsapp/templates` with `hub_mode=subscribe&hub_verify_token=valid_token&hub_challenge=12345`
     - Assert: status 200, content = '12345', header Content-Type = text/plain

  2. **test_verify_webhook_invalid_token_returns_403()**
     - Set valid token config
     - GET with wrong token
     - Assert: status 403, JSON error

  3. **test_webhook_invalid_signature_returns_401()**
     - POST with `X-Hub-Signature-256: sha256=invalid`
     - Assert: status 401

  4. **test_webhook_without_signature_returns_401()**
     - POST without X-Hub-Signature-256 header
     - Assert: status 401

  5. **test_webhook_valid_signature_dispatches_job()**
     - `Queue::fake()`
     - Set `Config::set('services.facebook.app_secret', 'test_secret')` (Meta uses same app_secret as Facebook for X-Hub-Signature-256)
     - Create valid payload JSON
     - Compute HMAC-SHA256: `hash_hmac('sha256', $rawBody, 'test_secret')`
     - POST with `X-Hub-Signature-256: sha256=<computed>`
     - Assert: status 200
     - Assert: `Queue::assertPushed(ProcessMetaTemplateWebhook::class, 1)`
     - Assert: job has correct meta_template_id and event values

  6. **test_webhook_handles_unknown_event_gracefully()**
     - POST with event 'UNKNOWN_EVENT' and valid signature
     - Assert: status 200 (controller dispatches job regardless; job handles the mapping)
     - `Queue::assertPushed(ProcessMetaTemplateWebhook::class)`

  **Helper method**:
  - `private function computeSignature(string $body, string $secret): string`:
    ```php
    return 'sha256=' . hash_hmac('sha256', $body, $secret);
    ```

  **Response assertions**: Use `$response->assertStatus(200)`, `$response->assertContent('12345')`, `$response->assertHeader('Content-Type', 'text/plain')`, `$response->assertJson(['status' => 'success'])`

  **Must NOT do**:
  - Do NOT use Pest syntax — project uses PHPUnit `assert*` methods
  - Do NOT make real HTTP calls to Meta
  - Do NOT mock config permanently — restore with `parent::tearDown()` or use `RefreshDatabase` properly

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Feature test file with multiple scenarios, HMAC computation, Queue faking. Requires understanding of Laravel testing patterns and HTTP assertions.
  - **Skills**: [`phpunit-best-practices`]
    - `phpunit-best-practices`: Feature test patterns, config mocking, HTTP assertions, Queue::fake() usage
  - **Skills Evaluated but Omitted**:
    - `laravel-best-practices`: Not needed for test files

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 2 (after Wave 1 implementation tasks)
  - **Blocks**: F1-F4
  - **Blocked By**: Tasks 1, 2, 3

  **References**:
  - `tests/Feature/WhatsAppTemplateServiceTest.php` — Existing WhatsApp template test file. Use for structure reference (class definition, trait usage, factory usage)
  - `tests/Feature/ExampleTest.php` — Basic test structure pattern for this project
  - `database/factories/WhatsAppTemplateFactory.php` — If exists, use for test record creation

  **External References**:
  - PHPUnit 11 `assert*` methods: assertStatus, assertJson, assertContent, assertHeader

  **Acceptance Criteria**:

  **QA Scenarios**:

  ```
  Scenario: All feature tests pass
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=WhatsAppTemplateWebhook --compact
    Expected Result: All 6 tests PASS (OK, 6 tests, 0 assertions/0 failures)
    Evidence: .sisyphus/evidence/task-4-all-tests-pass.txt

  Scenario: Single test runs
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=test_verify_webhook_valid_token_returns_challenge
    Expected Result: PASS
    Evidence: .sisyphus/evidence/task-4-verify-valid-test.txt

  Scenario: Signature verification test
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=test_webhook_invalid_signature_returns_401
    Expected Result: PASS
    Evidence: .sisyphus/evidence/task-4-invalid-sig-test.txt
  ```

  **Evidence to Capture**:
  - [ ] Full test output showing all passing
  - [ ] Individual filtered test runs

  **Commit**: YES (groups with tasks 2, 5)
  - Message: `test(whatsapp): add webhook controller feature tests`
  - Files: `tests/Feature/Http/Controllers/Api/WhatsAppTemplateWebhookTest.php`
  - Pre-commit: `php artisan test --filter=WhatsAppTemplateWebhook`

---

- [ ] 5. **Unit test: ProcessMetaTemplateWebhook job**

  **What to do**:
  Create `tests/Unit/Jobs/ProcessMetaTemplateWebhookTest.php` — unit tests for the job's business logic.

  **Test file structure**:
  ```php
  <?php

  namespace Tests\Unit\Jobs;

  use App\Enums\WhatsAppTemplateStatusEnum;
  use App\Jobs\ProcessMetaTemplateWebhook;
  use App\Models\WhatsAppTemplate;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Illuminate\Support\Facades\Cache;
  use Tests\TestCase;
  ```

  **Test cases**:

  1. **test_job_updates_template_to_approved()**
     - Create `WhatsAppTemplate` with `meta_template_id = 'test_123'`, `status = PENDING`
     - Create and run job: `(new ProcessMetaTemplateWebhook('test_123', 'APPROVED', null, 'PENDING'))->handle()`
     - Refresh model: `$template->refresh()`
     - Assert: `WhatsAppTemplateStatusEnum::APPROVED`, rejection_reason is null

  2. **test_job_updates_template_to_rejected_with_reason()**
     - Create template with `meta_template_id = 'test_456'`, `status = PENDING`
     - Run job with event='REJECTED', reason='Contains spam content'
     - Assert: `WhatsAppTemplateStatusEnum::REJECTED`, rejection_reason = 'Contains spam content'

  3. **test_job_handles_unknown_template_gracefully()**
     - Run job with `meta_template_id = 'nonexistent'`
     - No exception expected — job silently returns (catch block or early return)
     - Assert: no exception thrown

  4. **test_job_skips_processing_when_lock_held()**
     - Create template with `meta_template_id = 'test_locked'`, `status = PENDING`
     - Acquire lock: `$lock = Cache::lock("meta_template_webhook:test_locked", 30); $lock->get();`
     - Run job
     - Release lock: `$lock->release();`
     - Assert: template status is still PENDING (unchanged)

  5. **test_job_maps_all_event_types()**
     - Create templates with various meta_template_ids
     - Test each event mapping:
       - APPROVED → APPROVED
       - REJECTED → REJECTED
       - PENDING → PENDING
       - PENDING_DELETION → PENDING
       - FLAGGED → REJECTED
       - PAUSED → DISABLED
       - DISABLED → DISABLED
     - For each: run job, assert mapped correctly

  6. **test_job_skips_unchanged_status()**
     - Create template with status APPROVED
     - Run job with event='APPROVED'
     - Assert: status unchanged, no DB write overhead (check `updated_at` unchanged)

  **Use `dispatchNow()` for direct, synchronous execution**: `$job->handle()`
  **Use `DatabaseTransactions` trait** for test isolation (clean state per test)

  **Must NOT do**:
  - Do NOT use `Queue::fake()` here — this is a UNIT test, we call `handle()` directly
  - Do NOT make any HTTP calls
  - Do NOT test the queue infrastructure (that's a feature test concern)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Unit test file with 6+ scenarios covering event mapping, lock behavior, edge cases. Requires understanding of PHPUnit assertions and Laravel testing utilities.
  - **Skills**: [`phpunit-best-practices`]
    - `phpunit-best-practices`: Unit test patterns, data providers, model factory usage, Cache::lock testing
  - **Skills Evaluated but Omitted**:
    - `laravel-best-practices`: Not needed for test files

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 2 (with Task 4)
  - **Blocks**: F1-F4
  - **Blocked By**: Task 2

  **References**:
  - `tests/Unit/` — Check existing unit tests in this project for structure patterns
  - `app/Services/Meta/MetaTemplateService.php:206-215` — mapStatus() logic that should be replicated/reused
  - `app/Enums/WhatsAppTemplateStatusEnum.php` — All 6 status cases for event mapping assertions
  - `app/Models/WhatsAppTemplate.php` — Model methods like `isApproved()`, `isPending()` at lines 178-196

  **Acceptance Criteria**:

  **QA Scenarios**:

  ```
  Scenario: All unit tests pass
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=ProcessMetaTemplate --compact
    Expected Result: All 6 tests PASS (OK, 6 tests, 0 failures)
    Evidence: .sisyphus/evidence/task-5-all-tests-pass.txt

  Scenario: Event mapping test
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=test_job_maps_all_event_types
    Expected Result: PASS
    Evidence: .sisyphus/evidence/task-5-event-mapping.txt

  Scenario: Lock test
    Tool: Bash (php artisan)
    Steps:
      1. php artisan test --filter=test_job_skips_processing_when_lock_held
    Expected Result: PASS
    Evidence: .sisyphus/evidence/task-5-lock-test.txt
  ```

  **Evidence to Capture**:
  - [ ] Full test output showing all passing
  - [ ] Individual filtered test runs

  **Commit**: YES (groups with tasks 2, 4)
  - Message: `test(whatsapp): add ProcessMetaTemplateWebhook unit tests`
  - Files: `tests/Unit/Jobs/ProcessMetaTemplateWebhookTest.php`
  - Pre-commit: `php artisan test --filter=ProcessMetaTemplate`

---

## Final Verification Wave (MANDATORY — after ALL implementation tasks) (MANDATORY — after ALL implementation tasks)

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read file, curl endpoint, run command). For each "Must NOT Have": search codebase for forbidden patterns — reject with file:line if found. Check evidence files exist in .sisyphus/evidence/. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `php -l` on all changed files + `vendor/bin/pint --dirty --format agent`. Review all changed files for: `as any`/`@ts-ignore`, empty catches, console.log in prod, commented-out code, unused imports. Check AI slop: excessive comments, over-abstraction, generic names (data/result/item/temp).
  Output: `Build [PASS/FAIL] | Lint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Start from clean state. Execute EVERY QA scenario from EVERY task — follow exact steps, capture evidence. Test cross-task integration (features working together, not isolation). Test edge cases: empty state, invalid input, rapid actions. Save to `.sisyphus/evidence/final-qa/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | Edge Cases [N tested] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff (git log/diff). Verify 1:1 — everything in spec was built (no missing), nothing beyond spec was built (no creep). Check "Must NOT do" compliance. Detect cross-task contamination: Task N touching Task M's files. Flag unaccounted changes.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Commit Strategy

- **1**: `feat(whatsapp): add template status webhook controller` — `app/Http/Controllers/Api/WhatsAppTemplateWebhookController.php`, `routes/api.php`
- **2, 3, 4, 5**: `feat(whatsapp): add ProcessMetaTemplateWebhook job with tests` — `app/Jobs/ProcessMetaTemplateWebhook.php`, test files

---

## Success Criteria

### Verification Commands
```bash
php artisan test --filter=WhatsAppTemplateWebhook  # Expected: PASS (all tests)
php artisan test --filter=ProcessMetaTemplate  # Expected: PASS (all tests)
vendor/bin/pint --dirty --format agent  # Expected: PASS
php -l app/Http/Controllers/Api/WhatsAppTemplateWebhookController.php  # Expected: No syntax errors
php -l app/Jobs/ProcessMetaTemplateWebhook.php  # Expected: No syntax errors
```

### Final Checklist
- [ ] GET `/api/webhooks/whatsapp/templates?hub_mode=subscribe&hub_verify_token={token}&hub_challenge=123` returns `123` as plain text 200
- [ ] POST `/api/webhooks/whatsapp/templates` with valid `X-Hub-Signature-256` returns 200 and queues job
- [ ] POST with invalid signature returns 401
- [ ] Job processes `APPROVED` event → template status = APPROVED
- [ ] Job processes `REJECTED` event → template status = REJECTED + rejection_reason set
- [ ] Job uses `Cache::lock()` — concurrent duplicate events are handled safely
- [ ] Cache busted via `Cache::forget()` on `meta_templates:gym:*` pattern after status change
- [ ] All "Must Have" present
- [ ] All "Must NOT Have" absent
- [ ] All tests pass
