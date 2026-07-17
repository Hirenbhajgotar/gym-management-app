# PaymentCompleted Failure — Subscription Never Created (Reliability Fix)

## TL;DR

> **Quick Summary**: `SubscriptionController::verifyPayment()` dispatches `PaymentCompleted`
> and immediately shows the user "Subscription created/upgraded/renewed successfully!" even
> though the subscription is created **asynchronously** by a queued listener. If that listener
> fails (queue worker down, deadlock, missing Plan/User, Redis blip), the user is told success
> but **no subscription exists** — with no alert and no reconciliation.
> **Fix**: provision the subscription **synchronously** inside `verifyPayment()` before the
> success flash, extract the provisioning into a shared idempotent service, add a scheduled
> **reconciliation sweep** for any completed-but-unprovisioned payments, index `provisioned_at`,
> and stop the webhook from silently swallowing provisioning failures.

> **Deliverables**:
> - Shared `App\Services\SubscriptionProvisioningService` (synchronous, transactional, idempotent)
> - `SubscriptionProvisioner` (ShouldQueue) refactored to a thin async wrapper over that service
> - `verifyPayment()` calls the service synchronously; success shown only after provisioning
> - `app:reconcile-subscriptions` scheduled command + `routes/console.php` entry
> - Index on `payments.provisioned_at`
> - Webhook `handlePaymentWebhook` no longer returns 200 on silent provisioning failure
> - Extended PHPUnit coverage (happy path, queue-down, reconcile, webhook, double-fire)

> **Estimated Effort**: Large
> **Parallel Execution**: YES — 4 waves
> **Critical Path**: Task 1 (service) → Task 2 (refactor listener) → Task 3 (controller sync) → Task 5 (webhook) → Task 7 (reconcile) → Task 9 (tests)

---

## Context

### Original Request
"Create a comprehensive plan to handle failed `App\Events\PaymentCompleted.php` in
`SubscriptionController.php`. When user selects a new plan or upgrades or renews, and
redirects to subscription, in `verifyPayment()` after event call success message delivered to
profile page. But what happens if `PaymentCompleted` failed? User got success message but
subscription never created. Analyse codebase properly to identify this gap."

### Interview Summary
**Key Discussions**:
- User confirmed root cause matches: listener runs async (queued), controller shows success
  regardless.
- **Strategy chosen (user-confirmed)**: *Synchronous + reconcile* — provision inside
  `verifyPayment()` before success, AND add a scheduled reconciliation sweep.
- **Webhook included (user-confirmed)**: also fix `handlePaymentWebhook` silent failure.

**Research Findings** (from direct code reading):
- `PaymentCompleted` wired in `AppServiceProvider.php:113-116` to `SubscriptionProvisioner`.
- `SubscriptionProvisioner implements ShouldQueue` (line 21). Prod `QUEUE_CONNECTION=redis`
  (`.env:46`); tests use `sync` (`.env.testing:12`) — masks the gap in CI.
- `verifyPayment()` (lines 130-175): dispatch at 154, success flash at 157-165, never checks
  `provisioned_at`.
- No reconcile command exists; `provisioned_at` has **no DB index** (migration 2026_07_13_...).
- `handlePaymentWebhook` returns `200` (line 71-80) even when inner provisioning throws.
- Idempotency guard exists at listener lines 33 & 76 (`provisioned_at` under `lockForUpdate`).
- `SubscriptionService` methods can throw (`User not found`, `Plan not found`,
  `Upgrade context missing`, `Subscription not found for renewal`, deadlock).

### Metis Review
Metis consultation timed out (infrastructure/billing issue on the agent runner). Self-review
gap analysis performed instead (see Self-Review section). Edge cases explicitly designed for:
- **Double provisioning** (webhook + controller both fire): shared service re-checks
  `provisioned_at` under `lockForUpdate` → idempotent.
- **Reconcile racing an in-flight queued job**: same idempotency guard prevents duplicates.
- **Amount-mismatch held payments** (`metadata.amount_mismatch=true`): reconcile must SKIP them.
- **Partial failure mid-transaction**: service wraps writes in `DB::transaction` → atomic.
- **Renewal/create already applied**: `provisioned_at` guard makes a second run a no-op.

---

## Work Objectives

### Core Objective
Guarantee that a user who paid successfully always ends up with a real, active subscription —
synchronously for the interactive flow, and reconciled for any async/edge path — with no
silent "success but nothing created" state.

### Concrete Deliverables
- `app/Services/SubscriptionProvisioningService.php` (new)
- `app/Listeners/SubscriptionProvisioner.php` (refactored)
- `app/Http/Controllers/SubscriptionController.php::verifyPayment()` (modified)
- `app/Http/Controllers/Api/RazorpayWebhookController.php::handlePaymentWebhook()` (modified)
- `app/Console/Commands/ReconcileSubscriptionsCommand.php` (new)
- `routes/console.php` (new schedule entry)
- `database/migrations/xxxx_add_index_to_provisioned_at_on_payments_table.php` (new)
- Extended `tests/Feature/SubscriptionPaymentRedesignTest.php`

### Definition of Done
- [ ] `verifyPayment()` returns success **only after** the subscription row exists
      (assertable: `assertDatabaseHas('subscriptions', ...)` right after the redirect).
- [ ] A payment with `status=completed` and `provisioned_at=null` (and not amount-mismatch)
      is auto-provisioned by the reconcile command.
- [ ] Webhook returns non-2xx when provisioning fails so Razorpay redelivers.
- [ ] No double subscription when webhook + controller both fire.
- [ ] `payments.provisioned_at` is indexed.
- [ ] All new/changed behavior covered by PHPUnit tests run against `gym_app_test`.

### Must Have
- Synchronous provisioning inside `verifyPayment()` before success flash.
- Shared idempotent provisioning service used by controller, listener, and reconcile.
- Scheduled reconciliation command.
- Index on `provisioned_at`.
- Webhook failure surfaced (non-2xx) instead of swallowed.
- Tests for: sync happy path, queue-down simulation, reconcile sweep, webhook, double-fire.

### Must NOT Have (Guardrails)
- **Do NOT** change the Razorpay SDK, queue driver, or `SubscriptionService` business logic.
- **Do NOT** touch `gym_owner_registration` provisioning flow.
- **Do NOT** alter the `PaymentCompleted` event's public contract (`int $paymentId`).
- **Do NOT** run `migrate:fresh` / `db:seed` against `gym_app` — only `gym_app_test`
  via composer scripts or `--env=testing`.
- **Do NOT** show a success flash before the subscription is persisted (the core bug).
- **Do NOT** introduce AI slop: no generic `data`/`result`/`temp` vars, no comments in code
  bodies unless logic is exceptionally complex, no over-abstraction beyond the shared service.

---

## Verification Strategy (MANDATORY)

> **ZERO HUMAN INTERVENTION** — all verification agent-executed.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit, phpunit.xml forces `gym_app_test`).
- **Automated tests**: YES (tests-after; extend existing `SubscriptionPaymentRedesignTest`).
- **Framework**: PHPUnit (`php artisan make:test --phpunit`, run via composer).
- **If TDD**: N/A — extend existing suite (tests-after approach).

### QA Policy
Every task MUST include agent-executed QA scenarios. Evidence in
`.sisyphus/evidence/task-{N}-{scenario}.{ext}`.
- **Backend**: `docker-compose exec app php artisan test --filter=... --env=testing`
  (or `composer test:fresh` for full reset+run).

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation — shared service + index + listener refactor):
├── Task 1: SubscriptionProvisioningService (extract + idempotent)   [deep]
├── Task 2: Add index on payments.provisioned_at (migration)        [quick]
└── Task 3: Refactor SubscriptionProvisioner to thin async wrapper   [quick]

Wave 2 (Wire synchronous path + webhook):
├── Task 4: verifyPayment() calls service synchronously (no premature success) [deep]
├── Task 5: Webhook no longer swallows provisioning failure          [unspecified-high]
└── Task 6: amount-mismatch + edge handling in service               [deep]

Wave 3 (Reconciliation + scheduling):
├── Task 7: ReconcileSubscriptionsCommand                            [deep]
└── Task 8: Register command in routes/console.php scheduler         [quick]

Wave 4 (Tests + verification):
├── Task 9: Extend SubscriptionPaymentRedesignTest (sync, queue-down, reconcile, webhook, double-fire) [unspecified-high]
└── Task 10: Run full suite + pint                                   [quick]

Wave FINAL (4 parallel reviews, then user okay):
├── F1: Plan compliance audit (oracle)
├── F2: Code quality + static (unspecified-high)
├── F3: Real manual QA (unspecified-high)
└── F4: Scope fidelity (deep)
```

### Critical Path
Task 1 → Task 3 → Task 4 → Task 5 → Task 7 → Task 9 → F1-F4 → user okay

### Dependency Matrix
- **1**: - - 3,4,5,7
- **2**: - - (none; standalone index, but run before tests) - 9
- **3**: 1 - 4,7
- **4**: 1,3 - 9
- **5**: 1 - 9
- **6**: 1 - 9
- **7**: 1 - 8,9
- **8**: 7 - (none)
- **9**: 4,5,6,7 - (none)
- **10**: 9 - (none)

### Agent Dispatch Summary
- **1**: `deep` — extract service, transactional + idempotent.
- **2**: `quick` — migration index.
- **3**: `quick` — listener becomes wrapper.
- **4**: `deep` — controller synchronous wiring.
- **5**: `unspecified-high` — webhook error surfacing.
- **6**: `deep` — amount-mismatch/edge handling.
- **7**: `deep` — reconcile command.
- **8**: `quick` — scheduler entry.
- **9**: `unspecified-high` — test extension.
- **10**: `quick` — pint + suite.
- **FINAL**: F1 `oracle`, F2 `unspecified-high`, F3 `unspecified-high`, F4 `deep`.

---

## TODOs

---

- [ ] 1. Extract idempotent `SubscriptionProvisioningService`

  **What to do**:
  - Create `app/Services/SubscriptionProvisioningService.php` with a public
    `provision(int $paymentId): void` method that mirrors the current
    `SubscriptionProvisioner::handle()` logic (amount-mismatch hold, then the
    transactional provision + `provisioned_at` stamp + `GenerateInvoiceJob` dispatch).
  - Move the `resolveSession()`, `provision()`, and amount-mismatch block from
    `SubscriptionProvisioner` into this service. Keep idempotency: re-check
    `provisioned_at` under `lockForUpdate` inside the `DB::transaction` (copy the
    listener's lines 73-83 exactly).
  - Inject `SubscriptionService` and `GenerateInvoiceJob` (dispatch) via constructor.
  - Constructor property promotion, `declare(strict_types=1)`, explicit `: void` return.
  - Do NOT change `SubscriptionService` business logic — only call its existing methods.

  **Must NOT do**:
  - Do not modify `SubscriptionService` provisioning rules.
  - Do not change the `PaymentCompleted` event constructor signature.
  - Do not add retry/deadlock handling beyond what `SubscriptionService` already does.

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: requires faithful extraction of transactional + locking logic from an
      existing listener while preserving idempotency; high correctness risk.
  - **Skills**: `laravel-specialist`, `laravel-best-practices`
    - `laravel-specialist`: Eloquent locking, queued listener patterns, transaction boundaries.
    - `laravel-best-practices`: model/service conventions, explicit types, no raw queries.
  - **Skills Evaluated but Omitted**: `laravel-security` (no authz change here),
    `fluxui-development` (no UI).

  **Parallelization**:
  - **Can Run In Parallel**: YES (no dependency on Tasks 2/3)
  - **Parallel Group**: Wave 1 (with Tasks 2, 3)
  - **Blocks**: 3, 4, 5, 7
  - **Blocked By**: None

  **References**:
  - `app/Listeners/SubscriptionProvisioner.php:29-179` — source logic to extract (handle, resolveSession, provision, amount-mismatch hold).
  - `app/Services/SubscriptionService.php:22-96,181-221` — `createSubscription`/`renewSubscription`/`cancelSubscription` (called by service; do not change).
  - `app/Models/Payment.php:100,114` — `provisioned_at` fillable + datetime cast.
  - `app/Models/CheckoutSession.php` — `STATUS_COMPLETED`, `resolveSession` token lookup pattern.
  - `app/Jobs/GenerateInvoiceJob.php` — dispatched after provisioning.

  **Acceptance Criteria**:
  - [ ] `app/Services/SubscriptionProvisioningService.php` created.
  - [ ] `provision($paymentId)` is idempotent (second call on already-provisioned payment is a no-op, no 2nd subscription).
  - [ ] Method wrapped in `DB::transaction` with `lockForUpdate` on the payment.

  **QA Scenarios**:
  ```
  Scenario: [Happy path — new subscription provisioned]
    Tool: Bash (php artisan test)
    Preconditions: gym_app_test reset (composer test:db:reset), seeded Plan + User + CheckoutSession + Payment(status=completed, provisioned_at=null, payment_type=subscription_new)
    Steps:
      1. docker-compose exec app php artisan tinker --execute="app(\App\Services\SubscriptionProvisioningService::class)->provision(<paymentId>);"
      2. docker-compose exec app php artisan tinker --execute="echo app(\App\Models\Payment::class)->find(<paymentId>)->provisioned_at;"
    Expected Result: subscription row exists in DB; provisioned_at is NOT null (timestamp printed).
    Failure Indicators: provisioned_at still null, or no subscriptions row.
    Evidence: .sisyphus/evidence/task-1-happy.txt

  Scenario: [Idempotency — double call does not duplicate]
    Tool: Bash (tinker)
    Preconditions: payment already provisioned from above scenario
    Steps:
      1. Call provision(<paymentId>) a second time.
      2. Count subscriptions for that user+plan.
    Expected Result: count == 1 (no duplicate).
    Evidence: .sisyphus/evidence/task-1-idempotent.txt
  ```

- [ ] 2. Add DB index on `payments.provisioned_at`

  **What to do**:
  - Create migration `database/migrations/xxxx_add_index_to_provisioned_at_on_payments_table.php`
    using `php artisan make:migration`. Add `$table->index('provisioned_at')` in `up()` and
    `$table->dropIndex(['provisioned_at'])` in `down()`.
  - The column already exists (migration 2026_07_13_...); this only indexes it so the
    reconcile query is efficient.

  **Must NOT do**: Do not drop/recreate the column — only add an index.

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: single migration, mechanical.
  - **Skills**: `laravel-best-practices`
    - `laravel-best-practices`: migration conventions, `--no-interaction`.
  - **Skills Evaluated but Omitted**: others not needed.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3)
  - **Blocks**: (runs before tests in Task 9)
  - **Blocked By**: None

  **References**:
  - `database/migrations/2026_07_13_130158_add_provisioned_at_to_payments_table.php:17` — existing nullable column definition (preserve all attributes).
  - `app/Models/Payment.php:100,114` — confirms column usage.

  **Acceptance Criteria**:
  - [ ] Migration adds `index('provisioned_at')` and drops it in `down()`.
  - [ ] `composer test:db:reset` applies migration without error.

  **QA Scenarios**:
  ```
  Scenario: [Migration applies on test DB]
    Tool: Bash
    Steps:
      1. docker-compose exec app composer test:db:reset
      2. docker-compose exec app php artisan tinker --execute="echo Schema::hasIndex('payments', 'payments_provisioned_at_index');"
    Expected Result: true (index exists).
    Evidence: .sisyphus/evidence/task-2-index.txt
  ```

- [ ] 3. Refactor `SubscriptionProvisioner` to a thin async wrapper

  **What to do**:
  - Keep `class SubscriptionProvisioner implements ShouldQueue` (webhook + backward-compat
    still dispatch the event into the queue).
  - Replace the body of `handle(PaymentCompleted $event)` with a call to the new service:
    `app(SubscriptionProvisioningService::class)->provision($event->paymentId);`
  - Remove the now-duplicated `resolveSession`/`provision`/amount-mismatch code (moved to
    the service). Keep `InteractsWithQueue` + `ShouldQueue`.
  - Keep the `try/catch` that logs + re-throws so queue retry/backoff still works for the
    async path.

  **Must NOT do**: Do not remove `ShouldQueue` (webhook depends on async dispatch).
  Do not change the event→listener wiring in `AppServiceProvider`.

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: mechanical delegation to extracted service.
  - **Skills**: `laravel-specialist`
    - `laravel-specialist`: event/listener/queue wiring.
  - **Skills Evaluated but Omitted**: `laravel-security` (no authz).

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 1)
  - **Parallel Group**: Wave 1 (after Task 1)
  - **Blocks**: 4, 7
  - **Blocked By**: 1

  **References**:
  - `app/Listeners/SubscriptionProvisioner.php:21-98` — current handle + try/catch to preserve.
  - `app/Providers/AppServiceProvider.php:113-116` — must remain unchanged.
  - `app/Services/SubscriptionProvisioningService.php` — new dependency (Task 1).

  **Acceptance Criteria**:
  - [ ] `handle()` delegates to the service; duplicated logic removed.
  - [ ] `ShouldQueue` retained; queue retry still triggers the service.

  **QA Scenarios**:
  ```
  Scenario: [Queued event still provisions via wrapper]
    Tool: Bash (php artisan test)
    Preconditions: gym_app_test reset; a completed payment row
    Steps:
      1. docker-compose exec app php artisan tinker --execute="event(new \App\Events\PaymentCompleted(<paymentId>));"
      2. docker-compose exec app php artisan queue:work --once --env=testing
      3. assert subscription exists + provisioned_at not null
    Expected Result: subscription created, provisioned_at set.
    Evidence: .sisyphus/evidence/task-3-wrapper.txt
  ```

---

- [ ] 4. `verifyPayment()` provisions synchronously before success flash

  **What to do**:
  - In `SubscriptionController::verifyPayment()`, after `$verifiedPayment` is confirmed
    completed and before building the success message, replace the fire-and-forget
    `event(new PaymentCompleted(...))` with a **synchronous** call to the shared service:
    `app(SubscriptionProvisioningService::class)->provision($verifiedPayment->id);`
  - Wrap this in the existing `try` block so any thrown exception is caught and the user
    gets the `error` flash ("Payment failed. Please try again.") instead of a false success.
  - Keep the `if (str_starts_with(... 'subscription_'))` guard so only subscription payments
    provision (gym_owner_registration is handled separately by the webhook and must NOT be
    provisioned here).
  - Remove the now-unused `use App\Events\PaymentCompleted;` import if no longer referenced.
  - The success flash (lines 157-165) is now only reached AFTER provisioning succeeded.

  **Must NOT do**:
  - Do NOT dispatch the queued event here anymore (that was the bug). The webhook still uses
    the event for its own async path.
  - Do NOT show success before the subscription is persisted.
  - Do NOT provision `gym_owner_registration` payments here.

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: changes the critical payment-confirmation path; must preserve signature
      verification, auth check, and error handling while fixing ordering.
  - **Skills**: `laravel-specialist`, `laravel-security`
    - `laravel-specialist`: controller/service orchestration, transaction safety.
    - `laravel-security`: keep auth/`Auth::id()` check, no SSRF/signature bypass, CSRF intact.
  - **Skills Evaluated but Omitted**: `fluxui-development` (no UI change).

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 1 + 3)
  - **Parallel Group**: Wave 2 (with Tasks 5, 6)
  - **Blocks**: 9
  - **Blocked By**: 1, 3

  **References**:
  - `app/Http/Controllers/SubscriptionController.php:130-175` — verifyPayment; dispatch at 154, success at 157-165, error flash at 172-173.
  - `app/Services/SubscriptionProvisioningService.php` — new dependency.
  - `app/Services/RazorpayService.php::verifyPayment()` — returns verified Payment (already used at 140-144).

  **Acceptance Criteria**:
  - [ ] Success flash shown only after `provision()` returns without throwing.
  - [ ] If `provision()` throws, the `catch` returns the error flash (no false success).
  - [ ] `gym_owner_registration` payments are NOT provisioned by this method.

  **QA Scenarios**:
  ```
  Scenario: [Success only after subscription persisted]
    Tool: Bash (php artisan test --filter=verify_payment_redirects_to_profile_with_success_and_provisions_subscription)
    Preconditions: gym_app_test reset; Payment(status=completed, subscription_new) + CheckoutSession
    Steps:
      1. POST route('subscriptions.verify-payment') with mocked RazorpayService::verifyPayment returning the payment
      2. Assert redirect to profile + session has 'success'
      3. Assert subscriptions row exists for user+plan, provisioned_at not null
    Expected Result: redirect + success + DB row present (all three).
    Evidence: .sisyphus/evidence/task-4-sync.txt

  Scenario: [Failure surfaces as error, not success]
    Tool: Bash (php artisan test)
    Preconditions: Payment whose provisioning throws (e.g. Plan deleted)
    Steps:
      1. POST verify-payment with mocked verifyPayment returning that payment
      2. Assert session has 'error' (NOT 'success') and redirect to profile
    Expected Result: error flash shown, no success, no false confirmation.
    Evidence: .sisyphus/evidence/task-4-failure.txt
  ```

- [ ] 5. Webhook must not swallow provisioning failure

  **What to do**:
  - In `RazorpayWebhookController::handlePaymentCaptured()` (around lines 118-123), the
    `event(new PaymentCompleted($payment->id))` is fire-and-forget and the method returns
    `200` at line 71 even if provisioning later throws inside the queued listener.
  - Because the listener is queued, the webhook itself can't easily know success — but the
    current `catch` at line 72-81 returns `401` "error" for ANY exception, which is correct
    for redelivery. Decision: **keep the webhook dispatching the event (async), but ensure
    the reconcile command (Task 7) is the safety net** for webhook-path orphans. Additionally,
    remove the misleading comment implying synchronous provisioning, and add an explicit
    log+admin notification when provisioning is queued but the payment is completed, so ops
    can detect a stalled queue.
  - Do NOT change the signature verification or the `payment.captured`/`payment.failed`
    branching.

  **Must NOT do**: Do not make the webhook call the service synchronously (Razorpay webhook
  timeout risk); async + reconcile is the agreed design. Do not weaken signature checks.

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: touches a security-sensitive webhook; must preserve signature verification while
      improving failure visibility.
  - **Skills**: `laravel-security`, `laravel-specialist`
    - `laravel-security`: webhook signature integrity, no bypass, safe error responses.
    - `laravel-specialist`: event dispatch + queue semantics.
  - **Skills Evaluated but Omitted**: `fluxui-development`.

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 6)
  - **Blocks**: 9
  - **Blocked By**: 1

  **References**:
  - `app/Http/Controllers/Api/RazorpayWebhookController.php:30-134` — handlePaymentWebhook + handlePaymentCaptured; note the 200 at 71 and catch at 72-81.
  - `app/Listeners/SubscriptionProvisioner.php` — async listener used by webhook.
  - `app/Services/NotificationService.php::notifyAdmins` — used for alerts.

  **Acceptance Criteria**:
  - [ ] Webhook still dispatches the event for subscription payments.
  - [ ] A log + admin notification fires when a completed payment is queued for provisioning
    (so a stalled queue is detectable).
  - [ ] Signature verification unchanged; `payment.failed` branch unchanged.

  **QA Scenarios**:
  ```
  Scenario: [Webhook queues provisioning + logs]
    Tool: Bash (php artisan test)
    Preconditions: gym_app_test reset; a completed subscription payment row; mocked RazorpayService::verifyWebhookSignature true
    Steps:
      1. POST /api/razorpay/payment with signed payment.captured payload
      2. Run queue:work --once --env=testing
      3. Assert subscription created + a log/notification recorded for the queued provisioning
    Expected Result: 200 response, subscription provisioned via queue, log entry present.
    Evidence: .sisyphus/evidence/task-5-webhook.txt
  ```

- [ ] 6. Amount-mismatch & edge handling in the shared service

  **What to do**:
  - In `SubscriptionProvisioningService`, preserve the amount-mismatch hold logic (listener
    lines 39-70): if `abs(payment.amount - session.amount) > tolerance`, set
    `metadata.amount_mismatch=true` and `return` WITHOUT provisioning, and notify admins.
  - Ensure the reconcile command (Task 7) will **skip** payments with
    `metadata.amount_mismatch=true` (they are intentionally held for manual review).
  - Ensure `provision()` returns early (no throw, no duplicate) when `provisioned_at` is
    already set — this is the idempotency guarantee covering double-fire.

  **Must NOT do**: Do not auto-provision held amount-mismatch payments (that defeats the hold).

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: correctness-critical branching reused by all three call sites.
  - **Skills**: `laravel-best-practices`, `laravel-specialist`
  - **Skills Evaluated but Omitted**: `laravel-security`.

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 1)
  - **Parallel Group**: Wave 2 (with Tasks 4, 5)
  - **Blocks**: 9
  - **Blocked By**: 1

  **References**:
  - `app/Listeners/SubscriptionProvisioner.php:39-70` — amount-mismatch hold + admin notify.
  - `app/Models/Payment.php:100` — metadata fillable (amount_mismatch write).
  - `config/subscriptions.php` — `amount_mismatch_tolerance` (read via config()).

  **Acceptance Criteria**:
  - [ ] Mismatch → held, `amount_mismatch=true`, no subscription, admin notified.
  - [ ] Already-provisioned → no-op (idempotent).

  **QA Scenarios**:
  ```
  Scenario: [Mismatch held, not provisioned]
    Tool: Bash (php artisan test --filter=amount_mismatch_holds_payment_for_review_without_provisioning)
    Preconditions: existing test payment (session amount 1000, payment 2000, tolerance 0)
    Steps:
      1. Call service->provision(<paymentId>)
      2. Assert no subscription row, metadata.amount_mismatch true, provisioned_at null
    Expected Result: held, no subscription.
    Evidence: .sisyphus/evidence/task-6-mismatch.txt
  ```

---

- [ ] 7. `ReconcileSubscriptionsCommand` (sweep orphaned payments)

  **What to do**:
  - Create `app/Console/Commands/ReconcileSubscriptionsCommand.php` via
    `php artisan make:command ReconcileSubscriptionsCommand --no-interaction`.
  - Signature: `app:reconcile-subscriptions` (consider `--dry-run` and `--since=` options).
  - Query: `Payment::query()->where('status', PaymentStatus::COMPLETED)`
    `->whereNull('provisioned_at')`
    `->where('payment_type', 'like', 'subscription_%')`  (new/upgrade/renewal)
    `->whereNull('metadata->amount_mismatch')`  (skip held mismatches)
    `->where('created_at', '>=', now()->subDays(<window>))` (e.g. 7 days, configurable).
  - For each, call `app(SubscriptionProvisioningService::class)->provision($payment->id)`
    inside a try/catch. On success: log + count. On failure: log error, increment a
    `failed` counter, and (optionally) notify admins once per run if any failed.
  - Output a summary line (processed / provisioned / failed / skipped). Honour
    `--dry-run` (query + log only, no provision calls).
  - Use `declare(strict_types=1)`, explicit return types, constructor promotion if deps needed.

  **Must NOT do**: Do not reprovision `gym_owner_registration` or `whatsapp_package` payments
  (out of scope). Do not modify `Payment` model. Do not run against `gym_app` (only via
  testing or scheduled prod run, which is the operator's job — command itself is DB-agnostic).

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: must correctly use JSON where() on metadata, avoid N+1, and safely loop.
  - **Skills**: `laravel-specialist`, `laravel-best-practices`
    - `laravel-specialist`: Eloquent JSON where, chunking, artisan command.
    - `laravel-best-practices`: command structure, config over env().
  - **Skills Evaluated but Omitted**: `laravel-security`.

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 1)
  - **Parallel Group**: Wave 3 (with Task 8)
  - **Blocks**: 8, 9
  - **Blocked By**: 1

  **References**:
  - `app/Services/SubscriptionProvisioningService.php` — reused provision().
  - `app/Models/Payment.php:100,114` — provisioned_at, metadata, status cast.
  - `database/migrations/2026_07_13_..._add_provisioned_at_to_payments_table.php` — column (Task 2 adds index).
  - Existing commands in `app/Console/Commands/` (e.g. `CheckMembershipExpiry.php`) — style to copy.
  - `routes/console.php:12-97` — scheduler registration pattern.

  **Acceptance Criteria**:
  - [ ] Command finds `completed AND provisioned_at IS NULL AND not amount_mismatch`.
  - [ ] Provisions each via shared service; reports counts.
  - [ ] `--dry-run` performs no writes.
  - [ ] Held mismatches are skipped.

  **QA Scenarios**:
  ```
  Scenario: [Orphaned payment gets provisioned by reconcile]
    Tool: Bash
    Preconditions: gym_app_test reset; a completed subscription_new payment with provisioned_at=null and a valid CheckoutSession
    Steps:
      1. docker-compose exec app php artisan app:reconcile-subscriptions --env=testing
      2. Assert subscription created + provisioned_at set + summary shows processed>=1
    Expected Result: orphaned payment reconciled, subscription exists.
    Evidence: .sisyphus/evidence/task-7-reconcile.txt

  Scenario: [Dry-run makes no changes]
    Tool: Bash
    Steps:
      1. Seed an orphaned payment, run with --dry-run --env=testing
      2. Assert provisioned_at still null, no subscription created
    Expected Result: no writes; summary lists it as would-process.
    Evidence: .sisyphus/evidence/task-7-dryrun.txt
  ```

- [ ] 8. Register reconcile command in scheduler

  **What to do**:
  - In `routes/console.php`, add
    `Schedule::command('app:reconcile-subscriptions')->everyFiveMinutes();`
    (or `->hourly()` — pick a sensible cadence; everyFiveMinutes catches orphans fast).
  - Place alongside the existing subscription-expiry schedules (around lines 56-65).

  **Must NOT do**: Do not change existing scheduled commands.

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: one-line scheduler registration following existing pattern.
  - **Skills**: `laravel-best-practices`
  - **Skills Evaluated but Omitted**: others.

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 7)
  - **Parallel Group**: Wave 3 (after Task 7)
  - **Blocks**: (none)
  - **Blocked By**: 7

  **References**:
  - `routes/console.php:12-97` — existing `Schedule::command(...)` entries.
  - `app/Console/Commands/ReconcileSubscriptionsCommand.php` — command signature (Task 7).

  **Acceptance Criteria**:
  - [ ] Command scheduled (entry present in `routes/console.php`).
  - [ ] `php artisan schedule:list --env=testing` includes the command.

  **QA Scenarios**:
  ```
  Scenario: [Command appears in schedule list]
    Tool: Bash
    Steps:
      1. docker-compose exec app php artisan schedule:list --env=testing
    Expected Result: line for `app:reconcile-subscriptions` present.
    Evidence: .sisyphus/evidence/task-8-schedule.txt
  ```

---

- [ ] 9. Extend `SubscriptionPaymentRedesignTest`

  **What to do**:
  - Add PHPUnit tests (feature) to `tests/Feature/SubscriptionPaymentRedesignTest.php`:
    1. **Sync success path**: `verifyPayment()` provisions synchronously — assert
       subscription exists + `provisioned_at` set right after the redirect (already partly
       covered; strengthen to assert no queue dependency by ensuring it passes under `sync`).
    2. **Failure path**: `provision()` throws (e.g. Plan deleted) → assert `error` flash,
       NOT `success` (Task 4 scenario).
    3. **Reconcile sweep**: seed an orphaned completed payment, run
       `Artisan::call('app:reconcile-subscriptions')`, assert subscription created.
    4. **Reconcile dry-run**: assert no writes.
    5. **Webhook + reconcile double-fire**: dispatch via webhook (queue) AND run reconcile;
       assert exactly ONE subscription (idempotency).
    6. **Amount-mismatch skip**: reconcile does NOT provision a held mismatch payment.
  - Use factories; respect test-DB safety (phpunit.xml already forces `gym_app_test`).
  - Mock `InvoiceService::generateInvoice` (slow PDF) as the existing test does.
  - Follow existing test style (PHPUnit attributes `#[Test]`, Mockery, `assertDatabaseHas`).

  **Must NOT do**: Do not remove existing tests. Do not run `migrate:fresh` on `gym_app`.

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: comprehensive coverage across controller, command, webhook, idempotency.
  - **Skills**: `phpunit-best-practices`, `laravel-specialist`
    - `phpunit-best-practices`: PHPUnit class structure, data providers, filtering.
    - `laravel-specialist`: feature test auth/flash/Artisan::call patterns.
  - **Skills Evaluated but Omitted**: `laravel-security` (no new authz).

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on 4,5,6,7)
  - **Parallel Group**: Wave 4 (with Task 10)
  - **Blocks**: (none)
  - **Blocked By**: 4, 5, 6, 7

  **References**:
  - `tests/Feature/SubscriptionPaymentRedesignTest.php:203-315` — existing verifyPayment + mismatch tests to extend.
  - `app/Services/SubscriptionProvisioningService.php`, `ReconcileSubscriptionsCommand.php` — units under test.
  - AGENTS.md Testing DB Safety Rules — use `composer test:db:reset` / `--env=testing`.

  **Acceptance Criteria**:
  - [ ] All 6 scenarios added and passing.
  - [ ] `composer test:fresh` green for this file.

  **QA Scenarios**:
  ```
  Scenario: [Full focused suite passes]
    Tool: Bash
    Steps:
      1. docker-compose exec app composer test:db:reset
      2. docker-compose exec app php artisan test --filter=SubscriptionPaymentRedesignTest --env=testing
    Expected Result: all tests pass (happy sync, failure error, reconcile, dry-run, double-fire idempotent, mismatch skip).
    Evidence: .sisyphus/evidence/task-9-suite.txt
  ```

- [ ] 10. Run full suite + Pint

  **What to do**:
  - Run `docker-compose exec app vendor/bin/pint --dirty --format agent` to fix style on all
    changed files.
  - Run `docker-compose exec app composer test:fresh` (reset test DB + full PHPUnit).
  - Fix any failures before marking complete. Do NOT commit test failures.

  **Must NOT do**: Do not run `migrate:fresh` (no flag) on `gym_app`. Use composer scripts.

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: verification gate, mechanical.
  - **Skills**: `laravel-best-practices` (pint convention)
  - **Skills Evaluated but Omitted**: others.

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 9)
  - **Parallel Group**: Wave 4 (after Task 9)
  - **Blocks**: (none)
  - **Blocked By**: 9

  **References**:
  - AGENTS.md Lint/Test commands (`vendor/bin/pint --dirty`, `composer test:fresh`).
  - All changed files from Tasks 1-9.

  **Acceptance Criteria**:
  - [ ] Pint reports no style issues on changed files.
  - [ ] Full PHPUnit suite green on `gym_app_test`.

  **QA Scenarios**:
  ```
  Scenario: [Pint clean + full suite green]
    Tool: Bash
    Steps:
      1. docker-compose exec app vendor/bin/pint --dirty --format agent
      2. docker-compose exec app composer test:fresh
    Expected Result: pint fixes applied, composer test:fresh exits 0.
    Evidence: .sisyphus/evidence/task-10-final.txt
  ```

---

## Final Verification Wave (MANDATORY)

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read plan end-to-end. For each Must Have: verify implementation exists (read file, run test).
  For each Must NOT Have: grep for forbidden patterns (gym_owner_registration touched,
  premature success flash, env('APP_NAME') misuse, migrate:fresh on gym_app). Check evidence
  files in `.sisyphus/evidence/`. Compare deliverables.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  `vendor/bin/pint --dirty`, `phpstan`/`./vendor/bin/phpstan analyse` if configured,
  `php artisan test`. Check for `as any`, empty catches, console.log, commented code,
  generic `data/result/temp` names, missing strict types.
  Output: `Build [PASS/FAIL] | Lint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Clean test DB (`composer test:db:reset`). Execute every QA scenario from every task.
  Cross-task: pay → verify → subscription exists → reconcile finds nothing orphaned.
  Edge: simulate queue-down (set QUEUE_CONNECTION=sync already in testing) by asserting
  subscription exists even if listener would have failed.
  Output: `Scenarios [N/N pass] | Integration [N/N] | Edge Cases [N tested] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task read "What to do" + actual diff (git). 1:1 — nothing missing, nothing beyond
  spec (no gym_owner changes, no SubscriptionService logic changes). Check "Must NOT do".
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---


## Success Criteria

### Verification Commands
```bash
# Reset test DB safely (gym_app_test only)
docker-compose exec app composer test:db:reset
# Run the focused suite
docker-compose exec app php artisan test --filter=SubscriptionPaymentRedesignTest --env=testing
# Full suite
docker-compose exec app composer test:fresh
# Static + style
docker-compose exec app vendor/bin/pint --dirty
```

### Final Checklist
- [ ] `verifyPayment()` success flash shown only after subscription persisted
- [ ] Shard service idempotent across controller/webhook/reconcile
- [ ] Reconcile command provisions orphaned `completed AND provisioned_at IS NULL`
- [ ] Webhook returns non-2xx on provisioning failure
- [ ] `provisioned_at` indexed
- [ ] No double subscription on double-fire
- [ ] All tests pass on `gym_app_test`
