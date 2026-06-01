# WhatsApp Broadcast System Refactor — Parallel Redis Queue Architecture

## TL;DR

> **Quick Summary**: Replace the current single-job synchronous broadcast processing (N+1 queries, sequential per-recipient API calls, single-worker bottleneck) with a two-phase parallel architecture using Redis queue job batching, chunk-based parallelism, and bulk queries — eliminating N+1 entirely.
>
> **Deliverables**:
> - New `SendMessageChunkJob` — processes 50 recipients per job (parallel across workers)
> - Refactored `SendBroadcastJob` → becomes batch orchestrator (pre-resolves variables, dispatches chunk jobs)
> - Migration: `batch_id` on broadcasts table
> - Updated `WhatsAppBroadcastService` — simplified, delegates to new jobs
> - Updated `BroadcastOrchestrator` — bulk INSERT recipients (no chunk loop)
> - Updated `ProcessScheduledBroadcastsCommand` — uses new dispatch
> - Updated configs: queue names for separate worker pools
> - Tests: dispatch test, chunk job test, batch completion test
>
> **Estimated Effort**: Medium-Large
> **Parallel Execution**: YES — Wave-based with max 5 tasks per wave
> **Critical Path**: Migration → SendMessageChunkJob → SendBroadcastJob refactor → BroadcastOrchestrator → Tests → Worker config

---

## Context

### Original Request
The broadcast messaging system currently processes all recipients **synchronously in a single job**. With 200K–300K messages across 2000-3000 tenants, each using individual member lookups (N+1 queries) and sequential API calls, the system cannot scale. Users want:
- Parallel processing via separate `SendMessageJob` queue workers
- Zero N+1 queries during broadcast sending
- Bulk queries for all DB operations
- 100 members in 2-3 minutes, 1000+ proportionally (not more than 5 min)
- Utilise existing Redis infrastructure (docker local + live)

### Current Architecture (Problems)

| Component | Problem | Impact |
|-----------|---------|--------|
| `SendBroadcastJob` → `processBroadcast()` | Single synchronous `chunkById(100)` loop processes ALL recipients in one job | Blocks a single worker for hours; no parallelism |
| `processRecipient()` → `GymMember::with()->find()` | **N+1**: one member DB query per recipient | 200K recipients = 200K queries |
| `prepareVariables()` inside recipient loop | Same N+1: loads member + relations per recipient | 200K recipients = 200K x N queries |
| `$recipient->markAsSent()`/`markAsFailed()` | Individual UPDATE per recipient | 200K recipients = 200K UPDATEs |
| Timeout = 3600s | Job runs for up to an hour | Worker starvation |
| No progress tracking | UI shows "draft" until complete | No transparency |
| Single failure mode | Job fails at recipient 50K → ALL lost | No resume capability |

### Research Findings
- **Laravel `Bus::batch()`**: Supports thousands of jobs in a batch. `then()`, `catch()`, `finally()` callbacks run when ALL jobs complete. Callbacks are serialized closures — can capture primitives (int IDs) but not `$this`.
- **Redis queue**: Already default driver (`config/queue.php`). `retry_after = 90s`. Chunk jobs must finish within 90s (50 × ~500ms API time = ~25s → safe).
- **`after_commit: false`**: Jobs dispatch immediately — no transaction sync concerns.
- **Provider APIs (Twilio/Meta)**: No true bulk API. Each `sendTemplateMessage()` = one HTTP call. Parallelism must come from multiple workers.
- **No Horizon** installed currently. Workers use `php artisan queue:work`. Can add queue names via `--queue=` flag or install Horizon.
- **Broadcasts already dispatched via `SendBroadcastJob`** from `BroadcastOrchestrator` and `ProcessScheduledBroadcastsCommand` — only the internal `handle()` needs refactoring.

### Self-Gap Analysis
- **Assumption: Memory for 200K recipient variable resolution** — A recipient record with eager-loaded relations is ~2KB. 200K × 2KB = 400MB. Need to chunk the resolution phase too (load + resolve in batches of 5K, flush between batches).
- **Assumption: Job payload size** — 50 recipients × ~500 bytes (ID + phone + resolved variables) = ~25KB per job. Well within Redis 512MB limit.
- **Assumption: Bus::batch with 4000+ jobs** — Laravel handles this but the `job_batches` table tracks each job. No known issues at this scale.
- **Guardrail: Rate limiting from Meta/Twilio** — Meta WhatsApp API rate-limited to ~80 msg/s for out-of-context messages. With 10 workers each doing ~2 msg/s (500ms each) = 20 msg/s. Under the 80/s limit. Safe.

---

## Work Objectives

### Core Objective
Refactor the WhatsApp broadcast system to process 200K–300K messages across 2000+ tenants in **under 5 minutes** using parallel Redis queue jobs, eliminating all N+1 queries and reducing DB writes to bulk operations.

### Concrete Deliverables
1. New `app/Jobs/SendMessageChunkJob.php` — processes 50 recipients per job, runs on `broadcast` queue
2. Refactored `app/Jobs/SendBroadcastJob.php` — becomes batch orchestrator: pre-resolves variables, dispatches batch of chunk jobs
3. Database migration — add `batch_id` (nullable string) to `whatsapp_broadcasts`
4. Updated `app/Services/WhatsApp/BroadcastOrchestrator.php` — uses bulk recipient INSERT inside transaction
5. Simplified `app/Services/WhatsApp/WhatsAppBroadcastService.php` — delegates sending to chunk jobs
6. Updated provider messengers (`MetaMessagingService`, `TwilioMessagingService`)
7. Worker config update: named queues in `.env` + `queue:work` commands
8. Updated `ProcessScheduledBroadcastsCommand` — no changes needed if `SendBroadcastJob` signature preserved
9. PHPUnit tests for new chunk job, dispatch, and batch completion

### Definition of Done
- [ ] `php artisan test --filter=WhatsAppBroadcastRefactor` — all tests pass
- [ ] Broadcast with 100 recipients completes in < 3 minutes (with 4+ workers)
- [ ] Zero individual `GymMember::find()` queries during sending (verified via DB query log)
- [ ] All recipient status updates use single UPDATE per chunk (not per-recipient)
- [ ] Code passes `vendor/bin/pint --dirty`

### Must Have
- Separate worker queue for message sending (`broadcast` queue)
- Pre-resolve ALL template variables in one batched pass before dispatching chunk jobs
- Bulk-update recipient statuses at chunk level (not per-recipient)
- Job batching for completion tracking (`Bus::batch`)
- Graceful failure handling — one failed chunk doesn't kill the broadcast
- `after_commit` behaviour preserved (currently `false` — immediate dispatch)

### Must NOT Have (Guardrails)
- Do NOT change the `WhatsAppVariableResolver` class itself — only change how it's called (once per batch, not per-recipient)
- Do NOT modify the Twilio/Meta provider `sendTemplateMessage()` method signature
- Do NOT introduce new third-party packages — Horizon is optional, can use `queue:work --queue=broadcast`
- Do NOT break the existing `BroadcastRateLimiter` — preserve daily 2-broadcast limit per gym
- Do NOT change the Filament Broadcast page UI or form structure
- Do NOT remove the existing `SendBroadcastJob` class — refactor its `handle()` method

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit 11 configured)
- **Automated tests**: TDD — tasks follow test-first pattern
- **Framework**: PHPUnit 11
- **Coverage**: New job dispatch, chunk job processing, batch completion, variable resolution, bulk status update

### QA Policy
Every task includes agent-executed QA scenarios verified via `php artisan test` commands and tinker-based DB inspection.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation — Parallel):
├── Task 1: Migration + model updates [quick]
├── Task 2: Create SendMessageChunkJob [deep]
└── Task 3: Tests for SendMessageChunkJob [phpunit-best-practices]

Wave 2 (Core orchestration — Parallel, after Wave 1):
├── Task 4: Refactor SendBroadcastJob → batch orchestrator [deep]
├── Task 5: Update BroadcastOrchestrator — bulk INSERT [laravel-specialist]
├── Task 6: Simplify WhatsAppBroadcastService + messenger services [laravel-specialist]
├── Task 7: Tests for orchestration [phpunit-best-practices]
└── Task 8: Queue configuration + worker setup [quick]

Wave 3 (Integration — after Wave 2):
├── Task 9: Update ProcessScheduledBroadcastsCommand [quick]
├── Task 10: Integration test: end-to-end broadcast flow [phpunit-best-practices]
└── Task 11: Performance benchmark script [quick]

Wave FINAL (Verification — 4 parallel reviews, then explicit user okay):
├── F1: Plan compliance audit (oracle)
├── F2: Code quality review (unspecified-high)
├── F3: Real manual QA (unspecified-high)
└── F4: Scope fidelity check (deep)
```

### Critical Path
Task 1 → Task 2 → Task 4 → Task 10 → F1-F4

### Dependency Matrix
- **Task 1** (migration): blocks 2, 4, 5, 6, 10
- **Task 2** (chunk job): blocks 4, 7, 10
- **Task 3** (chunk tests): blocks 7
- **Task 4** (send broadcast orchestrator): blocks 6, 9, 10
- **Task 5** (orchestrator update): blocks 10
- **Task 6** (service simplify): blocks 10
- **Task 7** (orchestration tests): blocked by 3, 4, 5
- **Task 8** (queue config): blocks 10, 11
- **Task 9** (scheduled command): blocks 10
- **Task 10** (integration test): blocks 11
- **Task 11** (benchmark): blocked by 10

---

## TODOs

- [ ] 1. Create database migration + update models

  **What to do**:
  - Generate a new migration: `php artisan make:migration add_batch_id_to_whatsapp_broadcasts --no-interaction`
  - Add a nullable string column `batch_id` to `whatsapp_broadcasts` table:
    ```php
    Schema::table('whatsapp_broadcasts', function (Blueprint $table) {
        $table->string('batch_id', 36)->nullable()->after('status')->comment('Laravel job batch ID for Bus::batch tracking');
    });
    ```
  - Generate rollback: `Schema::table('whatsapp_broadcasts', function (Blueprint $table) { $table->dropColumn('batch_id'); });`
  - Update `app/Models/WhatsAppBroadcast.php`:
    - Add `'batch_id'` to the `$fillable` or `$guarded` array
    - Add a relationship to access batch status:
      ```php
      public function batch(): ?\Illuminate\Bus\Batch
      {
          return $this->batch_id ? Bus::findBatch($this->batch_id) : null;
      }
      ```
    - Add accessor for progress:
      ```php
      public function getProgressAttribute(): ?array
      {
          $batch = $this->batch();
          if (! $batch) return null;
          return [
              'total' => $batch->totalJobs,
              'pending' => $batch->pendingJobs,
              'failed' => $batch->failedJobs,
              'progress' => $batch->progress(),
          ];
      }
      ```
  - Run migration: `php artisan migrate`
  - Verify column exists: use `database-schema` tool

  **Must NOT do**:
  - Do NOT drop or modify any existing columns
  - Do NOT create a new table — only modify existing `whatsapp_broadcasts`
  - Do NOT add `resolved_variables` column — variables will be resolved in-memory and passed to chunk jobs

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Standard single-column migration + model updates
  - **Skills**: [`laravel-best-practices`, `laravel-specialist`]
    - `laravel-best-practices`: Schema conventions, model conventions
    - `laravel-specialist`: Artisan make:migration, Bus::findBatch

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3)
  - **Blocks**: Tasks 2, 4, 5, 6, 10
  - **Blocked By**: None (can start immediately)

  **References**:
  - `database/migrations/` — Existing migrations to follow naming/structure
  - `app/Models/WhatsAppBroadcast.php` — Model to update
  - Laravel docs on `Bus::findBatch()` — batch tracking API
  - `config/queue.php:batching` — Job batching table config

  **Acceptance Criteria**:
  - [ ] Migration creates `batch_id` varchar(36) nullable column on `whatsapp_broadcasts`
  - [ ] `php artisan migrate` runs without errors
  - [ ] `WhatsAppBroadcast` model has `batch()` method returning `?Batch`
  - [ ] `WhatsAppBroadcast` model has `progress()` accessor returning `?array`

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Migration creates batch_id column
    Tool: Bash (php artisan migrate)
    Preconditions: Clean migration state
    Steps:
      1. php artisan migrate
      2. Use database-schema to verify column exists
    Expected Result: whatsapp_broadcasts table has batch_id column (varchar, nullable)
    Evidence: .sisyphus/evidence/task-1-migration.txt

  Scenario: Model batch method returns null when no batch_id
    Tool: Bash (php artisan tinker)
    Preconditions: WhatsappBroadcast exists without batch_id
    Steps:
      1. $b = \App\Models\WhatsAppBroadcast::first()
      2. $b->batch()
    Expected Result: null
    Evidence: .sisyphus/evidence/task-1-no-batch.txt

  Scenario: Model progress accessor returns null when no batch_id
    Tool: Bash (php artisan tinker)
    Preconditions: Same broadcast
    Steps:
      1. $b = \App\Models\WhatsAppBroadcast::first()
      2. $b->progress
    Expected Result: null (or array if batch_id set)
    Evidence: .sisyphus/evidence/task-1-progress.txt
  ```

  **Evidence to Capture**:
  - [ ] task-1-migration.txt
  - [ ] task-1-no-batch.txt
  - [ ] task-1-progress.txt

  **Commit**: NO

- [ ] 2. Create `SendMessageChunkJob` — the worker that sends messages for a chunk of recipients

  **What to do**:
  - Create `app/Jobs/SendMessageChunkJob.php` using `php artisan make:job SendMessageChunkJob --no-interaction`
  - The job receives:
    - `public int $broadcastId` — broadcast this chunk belongs to
    - `public array $recipientChunk` — array of pre-resolved recipient data (already resolved, no DB lookups needed)
    - `public array $templateData` — template ID + resolved message body/components for sending
    - `public int $gymId` — for provider resolution
  - `$recipientChunk` structure (pre-resolved by the orchestrator):
    ```php
    [
        ['id' => 1, 'phone_number' => '+919876543210', 'member_name' => 'John'],
        ['id' => 2, 'phone_number' => '+919876543211', 'member_name' => 'Jane'],
        // ... 50 recipients
    ]
    ```
    Note: NO member_id or GymMember lookup needed — variables are already resolved by the orchestrator.
  - `$templateData` structure:
    ```php
    [
        'template_id' => 5,
        'provider_template_id' => 'welcome_template',
        'language' => 'en',
        'components' => [...],  // Pre-resolved template components
        'media_urls' => [...],
    ]
    ```
  - `handle()` logic:
    1. Resolve provider via `WhatsAppProviderFactory::create($this->gymId)`
    2. For each recipient in `$this->recipientChunk`:
       a. Call `$provider->sendTemplateMessage($recipient['phone_number'], $this->templateData)`
       b. Track success/failure in-memory arrays
    3. After all 50 recipients processed → **bulk update** recipient statuses:
       ```php
       $now = now();
       $cases = [];
       $ids = [];
       foreach ($this->recipientChunk as $i => $recipient) {
           $status = $results[$i]['success'] ? 'sent' : 'failed';
           $reason = $results[$i]['success'] ? null : $results[$i]['error'];
           $sid = $results[$i]['message_sid'] ?? null;
           $cases[] = "WHEN {$recipient['id']} THEN '{$status}'";
           $ids[] = $recipient['id'];
       }
       // Single bulk UPDATE query
       DB::statement("
           UPDATE whatsapp_broadcast_recipients
           SET status = CASE id {$this->implodeCases($cases)} END,
               failure_reason = CASE id ... END,
               sent_at = CASE WHEN status = 'sent' THEN NOW() ELSE NULL END
           WHERE id IN (" . implode(',', $ids) . ")
       ");
       ```
       (Use a helper method to build the CASE statement cleanly)
    4. Update broadcast sent/failed counts:
       ```php
       $sentCount = count(array_filter($results, fn($r) => $r['success']));
       $failedCount = count($results) - $sentCount;
       WhatsAppBroadcast::where('id', $this->broadcastId)
           ->incrementEach(['sent_count' => $sentCount, 'failed_count' => $failedCount]);
       ```
  - Use `ShouldQueue`, `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels` traits (from `make:job`)
  - Set `public $timeout = 90` — 90s to process 50 messages (50 × ~1.5s = 75s, safe margin)
  - Set `public $tries = 1` — do NOT auto-retry entire chunk (individual failures are tracked per-recipient)
  - Handle exceptions per-recipient, NOT at the job level — wrap each `sendTemplateMessage()` in try/catch
  - If the entire chunk fails (e.g., provider down), catch the exception, log it, mark all recipients as 'failed', and allow the job to fail gracefully
  - The job should dispatch on the `broadcast` queue:
    ```php
    public function __construct(...)
    {
        $this->onQueue('broadcast');
    }
    ```

  **Must NOT do**:
  - Do NOT perform ANY `GymMember` / Eloquent queries inside this job — all data is pre-resolved
  - Do NOT perform per-recipient DB updates — only bulk updates per chunk
  - Do NOT catch exceptions at the job level for individual message failures — track per-recipient
  - Do NOT use `SerializesModels` if we're passing plain arrays (not models) — verify trait usage
  - Do NOT import `WhatsAppBroadcastRecipient` model — use `DB::statement()` for bulk updates

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: New job class with critical bulk-query patterns, needs careful implementation
  - **Skills**: [`laravel-best-practices`, `laravel-specialist`]
    - `laravel-best-practices`: Job structure, queue configuration, timeout/tries
    - `laravel-specialist`: Bulk CASE UPDATE statements, DB::statement, provider factory

  **Parallelization**:
  - **Can Run In Parallel**: YES (multiple instances process different chunks simultaneously)
  - **Parallel Group**: Wave 1 (with Tasks 1, 3)
  - **Blocks**: Tasks 4, 7, 10
  - **Blocked By**: Task 1 (need batch_id column concept for context)

  **References**:
  - `app/Jobs/SendBroadcastJob.php` — Existing job patterns (timeout, dispatch, ShouldQueue)
  - `app/Providers/WhatsAppProviderFactory.php` — Provider resolution
  - `app/Providers/MetaWhatsAppProvider.php:sendTemplateMessage()` — API call signature
  - `app/Providers/TwilioWhatsAppProvider.php:sendTemplateMessage()` — API call signature
  - `app/Models/WhatsAppBroadcastRecipient.php` — Recipient model for status column values
  - Laravel docs on `DB::statement()` and bulk UPDATE patterns
  - Laravel docs on job queue configuration

  **Acceptance Criteria**:
  - [ ] `SendMessageChunkJob` class exists with constructor accepting broadcastId, recipientChunk (pre-resolved), templateData, gymId
  - [ ] Job sets `$timeout = 90` and `$tries = 1`
  - [ ] Job dispatches on `broadcast` queue
  - [ ] Job performs ZERO Eloquent `find()`/`with()` queries
  - [ ] Job uses a single `DB::statement()` UPDATE query per chunk (not per-recipient writes)
  - [ ] Job calls `WhatsAppBroadcast::incrementEach()` once per chunk for sent/failed counts

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Chunk job processes 50 pre-resolved recipients without DB lookups
    Tool: Bash (php artisan tinker)
    Preconditions: Broadcast + 50 recipients exist in DB, provider mocked
    Steps:
      1. Create test data: broadcast with 50 pending recipients
      2. Prepare $recipientChunk array from DB (id, phone_number, member_name only)
      3. Prepare $templateData with valid template info
      4. Dispatch job: SendMessageChunkJob::dispatch($broadcast->id, $recipientChunk, $templateData, $gymId)
      5. Run queue worker once: php artisan queue:work --once --queue=broadcast
      6. Check recipient statuses: DB::table('whatsapp_broadcast_recipients')->where('whatsapp_broadcast_id', $broadcastId)->get()
    Expected Result: All 50 recipients have status = 'sent' or 'failed' (not 'pending')
    Evidence: .sisyphus/evidence/task-2-chunk-processed.txt

  Scenario: Chunk job uses single UPDATE query (verify via DB::listen)
    Tool: Bash (php artisan tinker)
    Preconditions: DB::listen() capturing queries
    Steps:
      1. Attach DB::listen() to capture UPDATE queries
      2. Dispatch and process SendMessageChunkJob
      3. Inspect captured queries
    Expected Result: Exactly 1 UPDATE on whatsapp_broadcast_recipients (not 50 individual updates)
    Evidence: .sisyphus/evidence/task-2-bulk-update.txt

  Scenario: Chunk job handles individual message failures gracefully
    Tool: Bash (php artisan tinker)
    Preconditions: Provider mock throws on 3rd recipient
    Steps:
      1. Prepare 5 recipients, 3rd will fail
      2. Dispatch SendMessageChunkJob
      3. Process the job
      4. Check recipient statuses
    Expected Result: Recipients 1,2,4,5 = 'sent', Recipient 3 = 'failed' with failure_reason set
    Evidence: .sisyphus/evidence/task-2-partial-failure.txt
  ```

  **Evidence to Capture**:
  - [ ] task-2-chunk-processed.txt
  - [ ] task-2-bulk-update.txt
  - [ ] task-2-partial-failure.txt

  **Commit**: NO

- [ ] 3. Write PHPUnit tests for `SendMessageChunkJob`

  **What to do**:
  - Create `tests/Feature/WhatsAppBroadcastChunkJobTest.php` using `php artisan make:test WhatsAppBroadcastChunkJobTest --phpunit --no-interaction`
  - Use `RefreshDatabase` trait
  - Tests needed:
    1. `test_job_processes_fifty_recipients_and_updates_status_bulk()` — happy path: create broadcast + 50 pending recipients, mock provider's `sendTemplateMessage()` to return success, dispatch SendMessageChunkJob, process the job, assert all 50 recipients have `status = 'sent'`
    2. `test_job_uses_single_update_query_for_statuses()` — use `DB::listen()` to capture queries, assert exactly 1 UPDATE on `whatsapp_broadcast_recipients` and 1 UPDATE on `whatsapp_broadcasts`
    3. `test_job_handles_partial_failures_gracefully()` — create 5 recipients, mock provider to fail on the 3rd API call, assert recipients [0,1,3,4] = 'sent', recipient[2] = 'failed' with `failure_reason` set
    4. `test_job_dispatches_on_broadcast_queue()` — instantiate job, assert `$job->queue === 'broadcast'`
    5. `test_job_performs_zero_eloquent_member_queries()` — use `DB::listen()` to track all queries, assert zero SELECT queries on `gym_members` table (confirm no N+1)
  - Mock `WhatsAppProviderFactory` using `$this->mock()` in `setUp()`:
    ```php
    $this->mock(WhatsAppProviderFactory::class, function ($mock) {
        $mock->shouldReceive('create')->andReturn($this->mockProvider());
    });
    ```
  - The `mockProvider()` helper returns a mock that returns `['success' => true, 'message_sid' => 'SM'.fake()->uuid()]` for `sendTemplateMessage()`
  - For failure testing, the mock tracks call count and throws on the Nth call
  - Follow existing test patterns from `tests/Feature/WhatsAppBroadcastValidationTest.php`

  **Must NOT do**:
  - Do NOT make actual HTTP calls — always mock the provider
  - Do NOT test the orchestrator or SendBroadcastJob — only SendMessageChunkJob in isolation
  - Do NOT test batch completion — covered in Task 7

  **Recommended Agent Profile**:
  - **Category**: `phpunit-best-practices`
    - Reason: New test file with DB::listen, mocks, and query assertions
  - **Skills**: [`phpunit-best-practices`, `laravel-specialist`]
    - `phpunit-best-practices`: Mock patterns, DB::listen, test structure
    - `laravel-specialist`: Provider factory mocking, queue testing

  **Parallelization**:
  - **Can Run In Parallel**: YES (independent test file)
  - **Parallel Group**: Wave 1 (with Tasks 1, 2)
  - **Blocks**: Task 7
  - **Blocked By**: Task 2

  **References**:
  - `tests/Feature/WhatsAppBroadcastValidationTest.php` — Test patterns
  - `app/Jobs/SendMessageChunkJob.php` — Job under test
  - `app/Providers/WhatsAppProviderFactory.php` — Factory to mock

  **Acceptance Criteria**:
  - [ ] 5 test methods cover: bulk success, single UPDATE, partial failure, queue name, zero N+1
  - [ ] `php artisan test --compact --filter=WhatsAppBroadcastChunkJob` → all pass

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: All chunk job tests pass
    Tool: Bash (php artisan test)
    Preconditions: Clean database
    Steps:
      1. php artisan test --compact --filter=WhatsAppBroadcastChunkJob
    Expected Result: 5 tests pass, 0 failures
    Evidence: .sisyphus/evidence/task-3-chunk-tests-pass.txt

  Scenario: Zero N+1 test confirms no gym_members queries during chunk job
    Tool: Bash (php artisan test)
    Steps:
      1. php artisan test --compact --filter=test_job_performs_zero_eloquent_member_queries
    Expected Result: TEST PASS
    Evidence: .sisyphus/evidence/task-3-no-n-plus-one.txt
  ```

  **Evidence to Capture**:
  - [ ] task-3-chunk-tests-pass.txt
  - [ ] task-3-no-n-plus-one.txt

  **Commit**: NO

- [ ] 4. Refactor `SendBroadcastJob` → batch orchestrator

  **What to do**:
  - Rewrite `app/Jobs/SendBroadcastJob.php`'s `handle()` method
  - New flow replaces old `WhatsAppBroadcastService::processBroadcast()` call with batch orchestration:
    1. Load broadcast: `$broadcast = WhatsAppBroadcast::findOrFail($this->broadcastId)`
    2. Mark as 'sending': `$broadcast->update(['status' => 'sending', 'started_at' => now()])`
    3. Load ALL pending recipients with eager-loaded member data in **ONE query**:
       ```php
       $recipients = WhatsAppBroadcastRecipient::where('whatsapp_broadcast_id', $this->broadcastId)
           ->where('status', 'pending')
           ->with(['member.gym', 'member.membershipPlan', 'member.trainer', 'member.latestPayment'])
           ->get(['id', 'member_id', 'phone_number', 'member_name']);
       ```
       This single query replaces N+1 individual `GymMember::find()` calls.
    4. Load template: `$template = $broadcast->template`
    5. Pre-resolve ALL template variables IN MEMORY:
       ```php
       $resolver = app(WhatsAppVariableResolver::class);
       $resolvedRecipients = $recipients->map(function ($recipient) use ($resolver, $template, $broadcast) {
           $resolver->resolve($template, [
               'member' => $recipient->member,
               'custom' => $broadcast->message_variables ?? [],
           ]);
           return [
               'id' => $recipient->id,
               'phone_number' => $recipient->phone_number,
               'member_name' => $recipient->member_name,
           ];
       })->toArray();
       ```
       Note: $resolver->resolve() runs in PHP memory — ZERO DB queries.
    6. Build template data for provider API:
       ```php
       $templateData = [
           'template_id' => $template->provider_template_id,
           'language' => $template->language,
           'components' => $this->buildTemplateComponents($template),
           'media_urls' => $template->media_urls ?? [],
       ];
       ```
       Implement `buildTemplateComponents(WhatsAppTemplate $template): array` as a private method.
    7. Chunk resolved recipients into groups of 50:
       ```php
       $chunks = array_chunk($resolvedRecipients, 50);
       ```
    8. Create and dispatch job batch:
       ```php
       $jobs = [];
       foreach ($chunks as $chunk) {
           $jobs[] = new SendMessageChunkJob(
               broadcastId: $this->broadcastId,
               recipientChunk: $chunk,
               templateData: $templateData,
               gymId: $this->gymId,
           );
       }
       $batch = Bus::batch($jobs)
           ->name("broadcast-{$this->broadcastId}")
           ->then(function (Batch $batch) {
               $stats = DB::table('whatsapp_broadcast_recipients')
                   ->where('whatsapp_broadcast_id', $this->broadcastId)
                   ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
                   ->first();
               WhatsAppBroadcast::where('id', $this->broadcastId)->update([
                   'status' => 'completed',
                   'completed_at' => now(),
                   'sent_count' => $stats->sent,
                   'failed_count' => $stats->failed,
               ]);
           })
           ->catch(function (Batch $batch, Throwable $e) {
               Log::error("Broadcast {$this->broadcastId} batch failed", [
                   'error' => $e->getMessage(),
                   'failed_jobs' => $batch->failedJobs,
               ]);
               WhatsAppBroadcast::where('id', $this->broadcastId)->update([
                   'status' => 'failed',
               ]);
           })
           ->finally(function (Batch $batch) {
               WhatsAppBroadcast::where('id', $this->broadcastId)->update([
                   'failed_count' => $batch->failedJobs,
               ]);
           })
           ->dispatch();
       ```
       Note: Serialized closures capture `$this->broadcastId` as a primitive int — this is safe.
    9. Save batch ID: `$broadcast->update(['batch_id' => $batch->id]);`
  - Edge case: 0 pending recipients → mark as 'completed' immediately, no batch dispatch
  - Keep existing `ShouldQueue`, `InteractsWithQueue`, etc. traits
  - Set `$timeout = 120` (2 min) for variable resolution throughput
  - Keep `ShouldBeUnique` if already present — prevents duplicate dispatch
  - Add imports: `use Illuminate\Bus\Batch;`, `use Illuminate\Support\Facades\Bus;`, `use Illuminate\Support\Facades\DB;`, `use Illuminate\Support\Facades\Log;`

  **Must NOT do**:
  - Do NOT call `WhatsAppBroadcastService::processBroadcast()` — remove entirely
  - Do NOT call any provider's `sendBroadcast()` method — chunk jobs handle sending
  - Do NOT perform any per-recipient DB queries inside handle()
  - Do NOT change constructor signature (broadcastId, gymId)
  - Do NOT remove `ShouldBeUnique` if present

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Complex multi-step orchestration — Bus::batch, bulk loading, in-memory resolution, batch callbacks
  - **Skills**: [`laravel-best-practices`, `laravel-specialist`]
    - `laravel-best-practices`: Job architecture, batch orchestration, eager loading
    - `laravel-specialist`: Bus::batch with callbacks, chunking 200K+ records, DB::statement

  **Parallelization**:
  - **Can Run In Parallel**: NO (single orchestrator per broadcast)
  - **Parallel Group**: Wave 2 (with Tasks 5, 6, 7, 8)
  - **Blocks**: Tasks 6, 9, 10
  - **Blocked By**: Tasks 1, 2

  **References**:
  - `app/Jobs/SendBroadcastJob.php` — Job to refactor
  - `app/Jobs/SendMessageChunkJob.php` — Chunk job to dispatch
  - `app/Services/WhatsApp/WhatsAppVariableResolver.php` — Called once in memory
  - `app/Models/WhatsAppBroadcast.php` — Model with new batch_id
  - `app/Models/WhatsAppBroadcastRecipient.php` — Pending recipients query
  - Laravel docs: Bus::batch, batch callbacks

  **Acceptance Criteria**:
  - [ ] No old `processBroadcast()` call remains in handle()
  - [ ] Recipients loaded in ONE query with eager-loaded member relations
  - [ ] Variables resolved in-memory (zero additional DB queries)
  - [ ] Bus::batch dispatches one SendMessageChunkJob per 50 recipients
  - [ ] Batch ID saved on broadcast
  - [ ] Batch callbacks update broadcast status
  - [ ] 0 recipients → immediate 'completed'

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: 150 recipients → exactly 3 chunk jobs dispatched
    Tool: Bash (php artisan tinker)
    Preconditions: Bus::fake(), broadcast with 150 pending recipients
    Steps:
      1. Bus::fake([SendMessageChunkJob::class])
      2. Create broadcast + 150 recipients
      3. SendBroadcastJob::dispatch($id, $gymId)
      4. php artisan queue:work --once
      5. Bus::assertDispatched(SendMessageChunkJob::class, 3)
    Expected Result: 3 chunk jobs dispatched
    Evidence: .sisyphus/evidence/task-4-three-chunks.txt

  Scenario: 8 recipients → 1 chunk job
    Tool: Bash (php artisan tinker)
    Steps:
      1. Broadcast with 8 recipients, Bus::fake()
      2. Dispatch, process
      3. Bus::assertDispatched(SendMessageChunkJob::class, 1)
    Expected Result: 1 chunk job
    Evidence: .sisyphus/evidence/task-4-small-chunk.txt

  Scenario: 0 recipients → immediate completed
    Tool: Bash (php artisan tinker)
    Preconditions: 0 pending recipients
    Steps:
      1. Dispatch + process
      2. Check broadcast->status
    Expected Result: status = 'completed'
    Evidence: .sisyphus/evidence/task-4-zero-recipients.txt

  Scenario: Batch ID saved after dispatch
    Tool: Bash (php artisan tinker)
    Steps:
      1. Dispatch with 50 real recipients
      2. Process
      3. $broadcast->refresh()->batch_id
    Expected Result: batch_id is non-null string
    Evidence: .sisyphus/evidence/task-4-batch-id.txt
  ```

  **Evidence to Capture**:
  - [ ] task-4-three-chunks.txt
  - [ ] task-4-small-chunk.txt
  - [ ] task-4-zero-recipients.txt
  - [ ] task-4-batch-id.txt

  **Commit**: NO

- [ ] 5. Update `BroadcastOrchestrator` — bulk INSERT recipients

  **What to do**:
  - Update `app/Services/WhatsApp/BroadcastOrchestrator.php`:
    - Current recipient insertion may use per-member loop; replace with bulk INSERT
    - Inside `createAndDispatch()`, after broadcast record is created and target members queried:
      ```php
      $recipientsData = $members->map(function ($member) use ($broadcast) {
          return [
              'whatsapp_broadcast_id' => $broadcast->id,
              'member_id' => $member->id,
              'phone_number' => $member->phone,
              'member_name' => $member->name,
              'status' => 'pending',
              'created_at' => now(),
              'updated_at' => now(),
          ];
      })->toArray();
      
      foreach (array_chunk($recipientsData, 500) as $chunk) {
          WhatsAppBroadcastRecipient::insert($chunk);
      }
      $broadcast->update(['target_count' => count($recipientsData)]);
      ```
    - The 500-chunk is for MySQL parameter limit safety (~65000 params / 11 columns ≈ 5900 rows max, 500 is conservative)
    - Leave all validation, rate limiting, credit check, and scheduling logic unchanged

  **Must NOT do**:
  - Do NOT change method signature of `createAndDispatch()`
  - Do NOT change validation / rate check / credit check / schedule logic
  - Do NOT change member filtering/querying — only change insertion method

  **Recommended Agent Profile**:
  - **Category**: `laravel-specialist`
    - Reason: Targeted optimization of recipient insertion flow
  - **Skills**: [`laravel-best-practices`, `laravel-specialist`]
    - `laravel-best-practices`: Bulk insert patterns, transaction integrity
    - `laravel-specialist`: Model::insert(), chunked bulk operations

  **Parallelization**:
  - **Can Run In Parallel**: NO (sequential within Wave 2)
  - **Parallel Group**: Wave 2 (with 4, 6, 7, 8)
  - **Blocks**: Task 10
  - **Blocked By**: Task 1

  **References**:
  - `app/Services/WhatsApp/BroadcastOrchestrator.php` — Orchestrator to update
  - `app/Models/WhatsAppBroadcastRecipient.php` — Model for bulk insert
  - `app/Models/GymMember.php` — Member query (already done in orchestrator)

  **Acceptance Criteria**:
  - [ ] Recipients inserted via `WhatsAppBroadcastRecipient::insert()` in chunks of 500
  - [ ] `target_count` reflects actual inserted recipient count
  - [ ] Insertion occurs inside same DB transaction as broadcast creation
  - [ ] All existing rate/credit/schedule logic preserved unchanged

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Orchestrator bulk-inserts 200 recipients
    Tool: Bash (php artisan tinker)
    Preconditions: Gym with 200 members, valid template, sufficient credits
    Steps:
      1. Instantiate Orchestrator with deps
      2. Call createAndDispatch with valid data
      3. Count: WhatsAppBroadcastRecipient::where('whatsapp_broadcast_id', $id)->count()
    Expected Result: 200 recipients created
    Evidence: .sisyphus/evidence/task-5-bulk-insert.txt

  Scenario: target_count equals recipient count
    Tool: Bash (php artisan tinker)
    Steps:
      1. After createAndDispatch, check $broadcast->target_count
    Expected Result: target_count === 200
    Evidence: .sisyphus/evidence/task-5-target-count.txt
  ```

  **Evidence to Capture**:
  - [ ] task-5-bulk-insert.txt
  - [ ] task-5-target-count.txt

  **Commit**: NO

- [ ] 6. Simplify `WhatsAppBroadcastService` + provider messenger services

  **What to do**:
  - `WhatsAppBroadcastService` (`app/Services/WhatsApp/WhatsAppBroadcastService.php`):
    - This service currently has `processBroadcast($broadcastId)` which does the synchronous per-recipient loop
    - With the refactored architecture, this method is no longer called from `SendBroadcastJob`
    - Options:
      A. If `processBroadcast()` is only called from `SendBroadcastJob`, remove the method entirely (or deprecate it)
      B. If called from anywhere else, keep but delegate to `SendBroadcastJob` or throw deprecation notice
    - Verify callers via `grep -r "WhatsAppBroadcastService" app/ --include="*.php"`
    - Based on current code, `processBroadcast()` is called from:
      - `MetaMessagingService::sendBroadcast()` 
      - `TwilioMessagingService::sendBroadcast()`
    - Remove the `processBroadcast()` method body. Replace with:
      ```php
      /**
       * Broadcast sending is now handled by SendBroadcastJob → Bus::batch → SendMessageChunkJob.
       * This method is kept for backward compatibility but no longer performs synchronous processing.
       */
      public function processBroadcast(int $broadcastId): array
      {
          $broadcast = WhatsAppBroadcast::findOrFail($broadcastId);
          $broadcast->update(['status' => 'queued']);
          SendBroadcastJob::dispatch($broadcastId, $broadcast->gym_id);
          return ['status' => 'queued', 'message' => 'Broadcast dispatched to queue workers'];
      }
      ```
    - Keep `prepareRecipientFilterQuery()`, `prepareVariables()`, `getMemberScopes()` — they may be used elsewhere
  - `MetaMessagingService::sendBroadcast()` (`app/Services/Meta/MetaMessagingService.php`):
    - Currently calls `WhatsAppBroadcastService::processBroadcast()`
    - With above change, it now dispatches `SendBroadcastJob` via the service
    - No additional changes needed — the flow is: sendBroadcast() → WhatsAppBroadcastService::processBroadcast() → dispatches job
  - `TwilioMessagingService::sendBroadcast()` (`app/Services/Twilio/TwilioMessagingService.php`):
    - Same pattern — already delegates to `WhatsAppBroadcastService::processBroadcast()`
    - No additional changes needed
  - Verify no other code paths call `processBroadcast()` directly or depend on its synchronous behavior:
    - Run `grep -r "processBroadcast" app/ --include="*.php"` to find all callers
    - If `ProcessScheduledBroadcastsCommand` calls it, it will automatically use the new path since it dispatches `SendBroadcastJob`

  **Must NOT do**:
  - Do NOT delete the entire `WhatsAppBroadcastService` class — keep it for backward compatibility
  - Do NOT change method signatures
  - Do NOT break `sendTestMessage()` or any other method used by the Filament page

  **Recommended Agent Profile**:
  - **Category**: `laravel-specialist`
    - Reason: Service simplification, backward compatibility, grep-based impact analysis
  - **Skills**: [`laravel-best-practices`, `laravel-specialist`]
    - `laravel-best-practices`: Deprecation patterns, backward compatibility
    - `laravel-specialist`: Service layer design, grep-based call analysis

  **Parallelization**:
  - **Can Run In Parallel**: NO (sequential within Wave 2)
  - **Parallel Group**: Wave 2 (with 4, 5, 7, 8)
  - **Blocks**: Task 10
  - **Blocked By**: Task 4

  **References**:
  - `app/Services/WhatsApp/WhatsAppBroadcastService.php` — Service to simplify
  - `app/Services/Meta/MetaMessagingService.php` — Meta messenger
  - `app/Services/Twilio/TwilioMessagingService.php` — Twilio messenger
  - `app/Jobs/SendBroadcastJob.php` — The new orchestrator (from Task 4)

  **Acceptance Criteria**:
  - [ ] `WhatsAppBroadcastService::processBroadcast()` no longer performs synchronous per-recipient processing
  - [ ] Calling `processBroadcast()` dispatches `SendBroadcastJob` and returns immediately
  - [ ] All existing callers of `processBroadcast()` still work (return value changes from `['sent', 'failed']` to `['status' => 'queued']`)
  - [ ] `processRecipient()` / `prepareVariables()` methods preserved if used elsewhere

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: processBroadcast dispatches job instead of synchronous processing
    Tool: Bash (php artisan tinker)
    Preconditions: Existing broadcast with recipients
    Steps:
      1. Bus::fake()
      2. $service = app(WhatsAppBroadcastService::class)
      3. $result = $service->processBroadcast($broadcastId)
      4. Bus::assertDispatched(SendBroadcastJob::class)
    Expected Result: Job dispatched, result = ['status' => 'queued', ...]
    Evidence: .sisyphus/evidence/task-6-dispatches-job.txt

  Scenario: Backward compatible return value
    Tool: Bash (php artisan tinker)
    Steps:
      1. $result = $service->processBroadcast($id)
      2. isset($result['status']) && $result['status'] === 'queued'
    Expected Result: Returns array with 'status' key
    Evidence: .sisyphus/evidence/task-6-backward-compat.txt
  ```

  **Evidence to Capture**:
  - [ ] task-6-dispatches-job.txt
  - [ ] task-6-backward-compat.txt

  **Commit**: NO

- [ ] 7. Write orchestration & integration tests

  **What to do**:
  - Create `tests/Feature/WhatsAppBroadcastOrchestrationTest.php`:
    1. `test_send_broadcast_job_dispatches_chunk_jobs()` — create a broadcast with 100 recipients, mock provider, dispatch SendBroadcastJob, process it, assert batch dispatched with correct count of chunks
    2. `test_entire_broadcast_flow_with_bus_fake()` — use Bus::fake(), dispatch SendBroadcastJob, assert correct number of SendMessageChunkJob dispatched, assert batch callbacks work
    3. `test_batch_completed_updates_broadcast_status()` — process all chunk jobs, assert broadcast status = 'completed', assert sent_count !== 0
    4. `test_batch_failure_updates_broadcast_status()` — mock provider to throw on first call, process chunk, assert broadcast status = 'failed'
    5. `test_zero_recipients_immediately_completes()` — create broadcast with 0 pending recipients, process SendBroadcastJob, assert broadcast status = 'completed' without dispatching any jobs
  - Follow same mock patterns as Task 3
  - Use `Bus::fake()` at the test level, not global

  **Must NOT do**:
  - Do NOT test UI or Filament pages — only job orchestration
  - Do NOT mock Bus::batch itself — test the actual batch creation flow

  **Recommended Agent Profile**:
  - **Category**: `phpunit-best-practices`
    - Reason: Integration-level tests with Bus::fake, batch assertions
  - **Skills**: [`phpunit-best-practices`, `laravel-specialist`]
    - `phpunit-best-practices`: Bus::fake, batch assertions, integration patterns
    - `laravel-specialist`: Queue job testing, batch callbacks

  **Parallelization**:
  - **Can Run In Parallel**: YES (independent test file)
  - **Parallel Group**: Wave 2 (with 4, 5, 6, 8)
  - **Blocks**: Task 10
  - **Blocked By**: Tasks 3, 4, 5

  **References**:
  - `tests/Feature/WhatsAppBroadcastChunkJobTest.php` — Task 3 mock patterns
  - `app/Jobs/SendBroadcastJob.php` — Orchestrator under test
  - `app/Jobs/SendMessageChunkJob.php` — Dispatched by orchestrator

  **Acceptance Criteria**:
  - [ ] 5 test methods covering batch dispatch, flow, completion, failure, zero recipients
  - [ ] php artisan test --compact --filter=WhatsAppBroadcastOrchestrationTest → all pass

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: All orchestration tests pass
    Tool: Bash (php artisan test)
    Preconditions: Clean database, factory defaults
    Steps:
      1. php artisan test --compact --filter=WhatsAppBroadcastOrchestrationTest
    Expected Result: All 5 tests pass, 0 failures
    Evidence: .sisyphus/evidence/task-7-orchestration-tests-pass.txt

  Scenario: Zero recipients test
    Tool: Bash (php artisan test)
    Steps:
      1. php artisan test --compact --filter=test_zero_recipients_immediately_completes
    Expected Result: PASS
    Evidence: .sisyphus/evidence/task-7-zero-recipients.txt
  ```

  **Evidence to Capture**:
  - [ ] task-7-orchestration-tests-pass.txt
  - [ ] task-7-zero-recipients.txt

  **Commit**: NO

- [ ] 8. Configure queue names and worker setup

  **What to do**:
  - In `config/queue.php`, under `'connections.redis'`, add queue name configuration:
    ```php
    'queue' => function () {
        $default = env('REDIS_QUEUE', 'default');
        // Broadcast jobs use dedicated queue for worker isolation
        return $default;
    },
    ```
    Or keep as-is but ensure the env-var approach works.
  - The key change: `SendMessageChunkJob` and `SendBroadcastJob` both set `public $queue = 'broadcast';`
  - This is already defined in the chunk job (Task 2) and send broadcast job stays on `broadcast` queue
  - If not already present in `.env.example`, add:
    ```
    REDIS_QUEUE=default
    ```
  - Document supervisor configuration in a comment or note:
    ```
    # Supervisor worker for broadcast queue:
    # php artisan queue:work redis --queue=broadcast --sleep=1 --tries=1 --timeout=120
    ```
  - For production: ensure `php artisan queue:work redis --queue=broadcast --sleep=1 --tries=1 --timeout=120` runs as a supervisor process
  - Adjust the `--timeout` to 120 (matches SendBroadcastJob's $timeout). Chunk jobs have 90s timeout.
  - **IMPORTANT**: Update `config/queue.php` to set the `batching.database` to the app's main connection (MySQL, not the default sqlite):
    ```php
    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],
    ```
    This ensures Bus::batch tracking works reliably with the app's main database.

  **Must NOT do**:
  - Do NOT install Horizon unless user explicitly approves
  - Do NOT change the default queue connection
  - Do NOT modify other queue configs

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple config changes, env variables, documentation
  - **Skills**: [`laravel-best-practices`]
    - `laravel-best-practices`: Queue config conventions, worker setup patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES (independent config changes)
  - **Parallel Group**: Wave 2 (with 4, 5, 6, 7)
  - **Blocks**: Tasks 10, 11
  - **Blocked By**: None

  **References**:
  - `config/queue.php` — Queue config to review/update
  - `.env.example` — Add REDIS_QUEUE env var documentation
  - `app/Jobs/SendBroadcastJob.php` — Has $queue = 'broadcast'
  - `app/Jobs/SendMessageChunkJob.php` — Has $queue = 'broadcast'

  **Acceptance Criteria**:
  - [ ] SendBroadcastJob uses 'broadcast' queue
  - [ ] SendMessageChunkJob uses 'broadcast' queue
  - [ ] Queue config file has documented env var for queue name
  - [ ] Supervisor command documented

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Jobs dispatched on broadcast queue
    Tool: Bash (php artisan tinker)
    Preconditions: Bus::fake()
    Steps:
      1. Dispatch SendBroadcastJob
      2. Process job
      3. Bus::assertDispatched(SendBroadcastJob::class, function ($job) { return $job->queue === 'broadcast'; })
    Expected Result: Queue is 'broadcast'
    Evidence: .sisyphus/evidence/task-8-broadcast-queue.txt

  Scenario: Chunk job queue property exists
    Tool: Bash (grep)
    Steps:
      1. grep 'public \$queue' app/Jobs/SendMessageChunkJob.php
    Expected Result: 'public $queue = 'broadcast';'
    Evidence: .sisyphus/evidence/task-8-chunk-queue-prop.txt
  ```

  **Evidence to Capture**:
  - [ ] task-8-broadcast-queue.txt
  - [ ] task-8-chunk-queue-prop.txt

  **Commit**: NO

- [ ] 9. Update `ProcessScheduledBroadcastsCommand` if needed

  **What to do**:
  - Read `app/Console/Commands/ProcessScheduledBroadcastsCommand.php`
  - It currently finds scheduled broadcasts and calls `$messagingService->sendBroadcast()`, which delegates to `WhatsAppBroadcastService::processBroadcast()`, which now dispatches a job and returns immediately — this is the correct behavior
  - No code changes are needed inside the command itself
  - However, add a logging statement after dispatch:
    ```php
    Log::info("Scheduled broadcast {$broadcast->id} dispatched to queue", [
        'broadcast_id' => $broadcast->id,
        'gym_id' => $broadcast->gym_id,
        'recipients_count' => $broadcast->pendingRecipients()->count(),
    ]);
    ```
  - This helps with monitoring in production

  **Must NOT do**:
  - Do NOT change the schedule frequency (still every 2 minutes, still in routes/console.php)
  - Do NOT change the command signature

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple read + logging addition, verification-focused
  - **Skills**: [`laravel-best-practices`]
    - `laravel-best-practices`: Logging patterns, command convention compliance

  **Parallelization**:
  - **Can Run In Parallel**: YES (read-only + log line)
  - **Parallel Group**: Wave 3
  - **Blocks**: Task 10
  - **Blocked By**: Task 4

  **References**:
  - `routes/console.php` — Schedule config
  - `app/Console/Commands/ProcessScheduledBroadcastsCommand.php` — Command to update

  **Acceptance Criteria**:
  - [ ] Command still runs every 2 minutes (schedule unchanged in routes/console.php)
  - [ ] After processing a scheduled broadcast, a structured log entry is written

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Scheduled broadcast logs dispatch info
    Tool: Bash (php artisan tinker + Log::spy())
    Preconditions: Broadcast with schedule_at in past
    Steps:
      1. Log::spy()
      2. Create scheduled broadcast
      3. php artisan whataspp:process-scheduled
      4. Log::assertLogged('info', function ($entry) { return str_contains($entry['message'], 'dispatched'); })
    Expected Result: Log entry confirms dispatch
    Evidence: .sisyphus/evidence/task-9-scheduled-log.txt
  ```

  **Evidence to Capture**:
  - [ ] task-9-scheduled-log.txt

  **Commit**: NO

- [ ] 10. End-to-end integration test

  **What to do**:
  - Create `tests/Feature/WhatsAppBroadcastIntegrationTest.php`:
    1. `test_full_broadcast_pipeline()`:
       - Create gym + template + 100 members with valid phone numbers (use `$this->faker->e164PhoneNumber()`)
       - Create broadcast via Orchestrator `createAndDispatch()` with valid data
       - Assert: broadcast created, 100 recipients inserted with 'pending' status
       - Mock provider (to avoid actual API calls)
       - Dispatch and process SendBroadcastJob
       - Start queue worker to process all chunk jobs
       - Assert: all 100 recipients processed (status in 'sent' or 'failed')
       - Assert: broadcast 'batch_id' is not null
       - Assert: broadcast status is 'completed'
       - Assert: `sent_count + failed_count = 100`
    2. `test_mixed_success_failure_broadcast()`:
       - Mock provider to return failures for 10 out of 100 recipients randomly
       - Process all jobs
       - Assert: 90 sent, 10 failed
       - Assert: broadcast failed_count = 10
    3. `test_concurrent_broadcasts_dont_interfere()`:
       - Create 2 broadcasts for different gyms with 20 recipients each
       - Dispatch both
       - Process all jobs
       - Assert: each broadcast independently completes with correct counts
  - Use factories for data creation (gym, template, members)
  - Mock provider factory globally for the test class using `setUp()`:
    ```php
    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(WhatsAppProviderFactory::class, function ($mock) {
            $mock->shouldReceive('create')->andReturn($this->mockProvider());
        });
    }
    ```

  **Must NOT do**:
  - Do NOT test the Filament/admin UI — only job pipeline
  - Do NOT make actual HTTP API calls — always mock
  - Do NOT process real Redis queues — use `Bus::fake()` or `Queue::fake()`

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Full end-to-end pipeline test — real DB fakes, batch processing, multi-assert
  - **Skills**: [`phpunit-best-practices`, `laravel-specialist`]
    - `phpunit-best-practices`: Integration test patterns, concurrent flow testing
    - `laravel-specialist`: Queue testing, DB transaction management

  **Parallelization**:
  - **Can Run In Parallel**: NO (end-to-end pipeline test)
  - **Parallel Group**: Wave 3
  - **Blocks**: Task 11
  - **Blocked By**: Tasks 1, 4, 5, 6, 7, 8, 9

  **References**:
  - All previous test files (Tasks 3, 7)
  - `app/Jobs/SendBroadcastJob.php` — Orchestrator
  - `app/Jobs/SendMessageChunkJob.php` — Chunk job
  - `app/Services/WhatsApp/BroadcastOrchestrator.php` — Entry point
  - `app/Services/WhatsApp/WhatsAppBroadcastService.php` — Simplified service

  **Acceptance Criteria**:
  - [ ] 3 test methods: full pipeline, mixed success/failure, concurrent broadcasts
  - [ ] php artisan test --compact --filter=WhatsAppBroadcastIntegrationTest → all pass

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Full pipeline test passes
    Tool: Bash (php artisan test)
    Preconditions: Factory defaults populated
    Steps:
      1. php artisan test --compact --filter=WhatsAppBroadcastIntegrationTest
    Expected Result: All 3 tests pass
    Evidence: .sisyphus/evidence/task-10-integration-pass.txt

  Scenario: Concurrent broadcasts
    Tool: Bash (php artisan test)
    Steps:
      1. php artisan test --compact --filter=test_concurrent_broadcasts_dont_interfere
    Expected Result: Both broadcasts complete independently
    Evidence: .sisyphus/evidence/task-10-concurrent.txt
  ```

  **Evidence to Capture**:
  - [ ] task-10-integration-pass.txt
  - [ ] task-10-concurrent.txt

  **Commit**: NO

- [ ] 11. Performance benchmark + report

  **What to do**:
  - Create `tests/Performance/WhatsAppBroadcastBenchmarkTest.php`:
    1. `benchmark_1000_recipients()`:
       - Create broadcast with 1000 recipients (use factory, skip actual phone calls)
       - Mock provider to return success in 10ms (simulated)
       - Time the entire pipeline end-to-end
       - Assert: completions in under 30s for 1000 recipients (target: ~2.5s with 10 workers)
       - Log timing breakdown: dispatch time, batch creation time, job processing time
  - Use `$start = microtime(true)` for timing
  - Create `tests/Performance` directory if needed
  - Run with: `php artisan test --compact --filter=WhatsAppBroadcastBenchmarkTest`
  - Record results to a summary file: `.sisyphus/evidence/benchmark-results.txt`

  **Must NOT do**:
  - Do NOT make actual HTTP calls — use fast mocks
  - Do NOT include this test in CI — benchmark only

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Performance testing setup, timing instrumentation, report generation
  - **Skills**: [`laravel-specialist`, `phpunit-best-practices`]
    - `laravel-specialist`: Performance test patterns in Laravel
    - `phpunit-best-practices`: Timing assertions, data providers

  **Parallelization**:
  - **Can Run In Parallel**: NO (benchmark, measures actual wall time)
  - **Parallel Group**: Wave 4 (final, with F1-F4)
  - **Blocks**: None (informational)
  - **Blocked By**: Task 10

  **Acceptance Criteria**:
  - [ ] Benchmark test exists for 1000 recipients
  - [ ] Results logged to `.sisyphus/evidence/benchmark-results.txt`
  - [ ] Completion meets target: 1000 recipients in under 30s

  **QA Scenarios (MANDATORY):**

  ```
  Scenario: Benchmark completes under 30s
    Tool: Bash (php artisan test)
    Preconditions: Factory defaults, fast mock provider (10ms response)
    Steps:
      1. php artisan test --compact --filter=WhatsAppBroadcastBenchmarkTest
    Expected Result: Test passes, timing logged
    Evidence: .sisyphus/evidence/task-11-benchmark-complete.txt
  ```

  **Evidence to Capture**:
  - [ ] task-11-benchmark-complete.txt
  - [ ] benchmark-results.txt

  **Commit**: NO

---

## Final Verification Wave

> 4 review agents run in PARALLEL. ALL must APPROVE. Present consolidated results to user and get explicit "okay" before completing.
>
> **Do NOT auto-proceed after verification. Wait for user's explicit approval before marking work complete.**
> **Never mark F1-F4 as checked before getting user's okay.** Rejection or user feedback → fix → re-run → present again → wait for okay.

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read file, curl endpoint, run command). For each "Must NOT Have": search codebase for forbidden patterns — reject with file:line if found. Check evidence files exist in `.sisyphus/evidence/`. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `php artisan test --compact` + linter. Review all changed files for: empty catches, console.log in prod, commented-out code, unused imports. Check AI slop: excessive comments, over-abstraction.
  Output: `Build [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Start from clean state. Execute EVERY QA scenario from EVERY task — follow exact steps, capture evidence. Test cross-task integration (features working together, not isolation). Test edge cases: empty state, invalid input, rapid actions. Save to `.sisyphus/evidence/final-qa/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | Edge Cases [N tested] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff (git log/diff). Verify 1:1 — everything in spec was built (no missing), nothing beyond spec was built (no creep). Check "Must NOT do" compliance. Detect cross-task contamination: Task N touching Task M's files. Flag unaccounted changes.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Commit Strategy

- **1**: `feat(whatsapp): add batch_id column and Bus::batch relationship to WhatsAppBroadcast`
  - Files: database/migrations/*_add_batch_id_to_whatsapp_broadcasts.php, app/Models/WhatsAppBroadcast.php
  - Pre-commit: php artisan test --compact --filter=task_1_migration
- **2**: `feat(whatsapp): add SendMessageChunkJob for parallel broadcast processing`
  - Files: app/Jobs/SendMessageChunkJob.php
  - Pre-commit: php artisan test --compact --filter=WhatsAppBroadcastChunkJob
- **3**: `test(whatsapp): add SendMessageChunkJob tests`
  - Files: tests/Feature/WhatsAppBroadcastChunkJobTest.php
  - Pre-commit: php artisan test --compact --filter=WhatsAppBroadcastChunkJob
- **4**: `refactor(whatsapp): convert SendBroadcastJob to batch orchestrator with Bus::batch`
  - Files: app/Jobs/SendBroadcastJob.php
  - Pre-commit: php artisan test --compact --filter=WhatsAppBroadcastOrchestration
- **5**: `perf(whatsapp): bulk insert broadcast recipients instead of per-member loop`
  - Files: app/Services/WhatsApp/BroadcastOrchestrator.php
  - Pre-commit: php artisan test --compact --filter=WhatsAppBroadcastOrchestration
- **6**: `refactor(whatsapp): simplify WhatsAppBroadcastService to dispatch job instead of sync processing`
  - Files: app/Services/WhatsApp/WhatsAppBroadcastService.php
  - Pre-commit: php artisan test --compact --filter=WhatsAppBroadcastOrchestration
- **7**: `test(whatsapp): add integration tests for broadcast batch orchestration`
  - Files: tests/Feature/WhatsAppBroadcastOrchestrationTest.php
  - Pre-commit: php artisan test --compact --filter=WhatsAppBroadcastOrchestration
- **8** (groups with 4): `config(whatsapp): configure broadcast queue name and worker setup`
  - Files: config/queue.php, .env.example
- **9** (groups with 4): `refactor(whatsapp): add dispatch logging to scheduled broadcast command`
  - Files: app/Console/Commands/ProcessScheduledBroadcastsCommand.php
- **10**: `test(whatsapp): add end-to-end integration test for broadcast pipeline`
  - Files: tests/Feature/WhatsAppBroadcastIntegrationTest.php
  - Pre-commit: php artisan test --compact --filter=WhatsAppBroadcastIntegration
- **11**: `test(whatsapp): add performance benchmark test for broadcast pipeline`
  - Files: tests/Performance/WhatsAppBroadcastBenchmarkTest.php

---

## Success Criteria

### Verification Commands
```bash
# Run the full integration test suite
php artisan test --compact --filter=WhatsAppBroadcast

# Run benchmark
php artisan test --compact --filter=WhatsAppBroadcastBenchmark

# Expected output: All tests PASS, benchmark under 30s
```

### Final Checklist
- [ ] All "Must Have" present in implementation files
- [ ] All "Must NOT Have" absent (no N+1, no resolved_variables column, no old sync flow)
- [ ] All tests pass (orchestration, chunk, integration)
- [ ] Benchmark confirms 1000 recipients in under 30s
- [ ] No per-recipient member queries — zero N+1 confirmed
- [ ] Bus::batch callbacks update broadcast status correctly
- [ ] Both providers (Meta, Twilio) work with new pipeline
- [ ] Scheduled broadcasts dispatch correctly
