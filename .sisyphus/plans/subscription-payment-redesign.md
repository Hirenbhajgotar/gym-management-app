# Subscription & Payment System Redesign — Work Plan

**Author:** Sisyphus · **Date:** 2026-07-13 · **Status:** Draft (pending Momus review)

## 1. Goal & Scope

Rewrite the payments/subscriptions flow to make it **correct, idempotent, and auditable**, fixing the gaps found in review (G1–G12). The redesign keeps Razorpay as the PSP and Filament/Livewire as the UI; it changes the *orchestration* layer only.

### Gaps being fixed (with severity)

| ID | Sev | Gap | Fix phase |
|----|-----|-----|-----------|
| G1 | 🔴 | Renewal charges the **stale stored price** (`subscription->price`) instead of the current plan price | P3 |
| G2 | 🔴 | **New/upgrade subscriptions are never created if the user doesn't return** from Razorpay (webhook bails on missing `subscription_id`) | P2 |
| G3 | 🔴 | **No idempotency** on renewal → double-renew / double period on webhook retry or redirect+webhook | P2 |
| G4 | 🔴 | Webhook **misroutes upgrades as renewals** (calls `renewSubscription` for all `subscription_*` types) | P2 |
| G5 | 🟠 | Renewal period starts from a **stale past date** (lapsed time lost) | P3 |
| G6 | 🟠 | **Grace period is dead code** (expiry job never sets it) | P4 |
| G7 | 🟡 | **Float paisa truncation** `(int)($amount*100)` | P4 |
| G8 | 🟡 | **No amount reconciliation** at verification | P4 |
| G9 | 🟡 | Session-key checkout state is **fragile / cross-contaminating** | P1 |
| G10| 🟡 | **Upgrade not prorated** (full price charged, remaining credit lost) | P3 |
| G11| 🟡 | **Two divergent webhook endpoints** | P2 |
| G12| 🟢 | **No plan-price versioning** for audit | P0 |

### Non-goals
- Switching PSP / payment gateway.
- Reworking the Filament admin UI.
- Auto-recurring billing engine (out of scope; noted as future work).

---

## 2. Target Architecture

```
ProfileController (select/upgrade/renew)
        │  creates a signed, single-use CheckoutSession (DB row) + redirects with token
        ▼
SubscriptionController::checkout (reads CheckoutSession by token)
        │  resolves amount via PriceResolver, creates Razorpay order, stores Payment(pending)
        ▼
Razorpay modal  ── user pays ──┐
                               │
            ┌──────────────────┴───────────────────┐
            ▼                                      ▼
   CLIENT REDIRECT                          RAZORPAY WEBHOOK
   subscriptions/verify-payment             (SINGLE endpoint: RazorpayWebhookController)
        │  verify sig, mark Payment completed       │  verify sig, mark Payment completed
        │  dispatch PaymentCompleted                │  dispatch PaymentCompleted
        └───────────────┬───────────────────────────┘
                        ▼
            PaymentCompleted event
                        ▼
            SubscriptionProvisioner  (idempotent via payments.provisioned_at + lockForUpdate)
               ├─ subscription_new     → createSubscription()
               ├─ subscription_upgrade → cancel + createSubscription()  (prorated amount)
               └─ subscription_renewal → renewSubscription(sub, chargedAmount)
                        ▼
            GenerateInvoiceJob (from Payment.amount — source of truth)
```

**Single source of truth for money = `Payment.amount`** (what was actually charged). `Subscription.price` becomes *last charged price*, written at every charge event.

---

## 3. Data Model Changes

### 3.1 `checkout_sessions` (new) — fixes G9
Columns: `id`, `token` (unique, 32-char random), `user_id`, `type` (`new|upgrade|renewal|gym_owner_registration`), `plan_id` (nullable), `subscription_id` (nullable), `billing_cycle`, `amount` (decimal:2), `currency` (enum), `status` (`pending|completed|expired`), `expires_at`, `metadata` (json), timestamps. Index `token`, `user_id`.

### 3.2 `plan_prices` (new) — fixes G12
Versioned pricing. Columns: `id`, `plan_id`, `currency` (enum), `price_monthly` (decimal:2), `price_yearly` (decimal:2), `effective_from`, `effective_to` (nullable). A Plan **always** has exactly one open-ended row (`effective_to = null`). `PlanObserver` inserts a new row (closing the previous `effective_to`) whenever `price_yearly`/`price_monthly`/`currency` change.

### 3.3 `payments.provisioned_at` (new nullable timestamp) — fixes G3
Set inside the provisioner transaction; guards against double-provisioning.

### 3.4 `config/subscriptions.php` (new)
`grace_days` (default 7), `default_billing_cycle` (`yearly`), `proration_enabled` (bool), `amount_mismatch_tolerance` (int paisa, default 0).

### 3.5 Model updates
- `Plan`: add `prices()` hasMany; `currentPrice(Cycle)` helper reading open `plan_prices` row.
- `Subscription`: **remove `getCurrentPrice()`** (defined but unused); `renewSubscription()` gains a `$chargedAmount` param and refreshes features.
- `Payment`: add `provisioned_at` cast; add `markProvisioned()` helper.

---

## 4. Phases & Atomic Tasks

Each task lists **files**, **actions**, and **verification** (all tests run on the *test* DB via `composer test:db:reset` / `composer test:fresh` per AGENTS.md safety rules — never `migrate:fresh` on `gym_app`).

### Phase 0 — Pricing source of truth (G12, G1 foundation)
- **T0.1** Create `config/subscriptions.php` + `ServiceProvider`/`bootstrap/providers.php` registration (use `config()` not `env()`).
- **T0.2** Migration + `PlanPrice` model + factory; `Plan::prices()`; `PlanObserver` (or model `saved` event) that, on `price_yearly|price_monthly|currency` dirty, closes the current `plan_prices` row (`effective_to=now`) and opens a new one. Seed an initial `plan_prices` row for every existing plan (one-time command or seeder step).
- **T0.3** `PriceResolver` service: `resolve(Plan $plan, string $cycle, ?Carbon $at = null): array{amount:string, currency}` reading the effective `plan_prices` row; falls back to `Plan` columns for legacy. Unit-test with factory states.
- **Verify:** unit test `PriceResolver` returns new price after a price change; `PlanObserver` creates a versioned row.

### Phase 1 — CheckoutSession (G9)
- **T1.1** Migration + `CheckoutSession` model + factory + scopes (`valid()`, `forUser($id)`); auto-expire via `expires_at` check in scope / scheduled prune.
- **T1.2** `ProfileController::selectPlan/upgradePlan/renewPlan`: instead of writing session keys, create a `CheckoutSession` (status `pending`, `expires_at = now()->addHour()`) and redirect to `subscriptions.checkout` **with the token** (`?token=...`). Remove `clearSessionData()` session-key logic (keep `session()->forget` only if other flows need it).
- **T1.3** `SubscriptionController::checkout()`: replace `determineCheckoutType()` (session-based) with token resolution — load `CheckoutSession::valid()->where('token',$token)->forUser(auth()->id())->firstOrFail()`; branch on `type`. Each handler reads `plan`/`subscription` from the session row. Remove `clearSessionData()` reliance for routing.
- **T1.4** Update any Blade/redirect passing the token (profile-controller.blade, gym-owner flow). Keep `completePendingPayment` working by resolving via `CheckoutSession` or Payment.
- **Verify:** feature test — starting a renewal creates a `CheckoutSession`; second tab with stale state cannot misroute; expired token → 404/abort.

### Phase 2 — Single idempotent webhook + provisioner (G2, G3, G4, G11)
- **T2.1** Migration: `payments` add `provisioned_at` nullable timestamp.
- **T2.2** `PaymentCompleted` event (carries `paymentId`); dispatched from exactly one helper used by both webhook and redirect.
- **T2.3** `SubscriptionProvisioner` (queueable listener) — the **only** place subscriptions are mutated post-payment. Inside `DB::transaction` with `Payment::lockForUpdate()`:
  1. reload payment; **if `provisioned_at` set → return (idempotent).**
  2. resolve `CheckoutSession` / metadata to get `plan_id`, `subscription_id`, `billing_cycle`, `charged_amount`.
  3. branch:
     - `subscription_new` → `createSubscription(user, plan, [billing_cycle, trial_days])`.
     - `subscription_upgrade` → `cancelSubscription(old, immediately:true)` + `createSubscription(user, newPlan, [billing_cycle, trial_days=0])` (proration handled in T3.3 amount; or `changePlan(prorate:true)`).
     - `subscription_renewal` → `renewSubscription(sub, chargedAmount)`.
  4. set `provisioned_at = now()`; dispatch `GenerateInvoiceJob($payment)`.
- **T2.4** Consolidate webhooks: make `RazorpayWebhookController` the **single** endpoint. It verifies signature, marks `Payment` completed (idempotent: only if not already), and dispatches `PaymentCompleted` for **all** `subscription_*` / `whatsapp_package` / `gym_owner_registration` types (move gym-owner + whatsapp provisioning into their own idempotent listeners or keep inline but route through the same event). **Delete `PaymentController::handleWebhook`** (and its `routes/web.php` registration) to remove the divergent path. Keep `RazorpayWebhookController` returning 200 only after success (do NOT swallow exceptions with 200 — let Razorpay retry on real failure, but guard idempotently).
- **T2.5** `SubscriptionController::verifyPayment` (client redirect): verify signature, mark completed, dispatch `PaymentCompleted`. **Remove** direct `processRenewal/processUpgrade/processNewSubscription` service calls (the provisioner does the work). Keep `clearSessionData()`.
- **Verify:** feature test — (a) webhook delivered **twice** for a renewal → subscription period extended **once**; (b) new subscription is created even when **only the webhook** fires (no redirect) → fixes G2; (c) upgrade webhook creates a new plan subscription, not a renewal of the old → fixes G4.

### Phase 3 — Pricing correctness at charge time (G1, G5, G10)
- **T3.1** All three checkout handlers compute `amount` via `PriceResolver::resolve(plan, cycle)` and store it on the `CheckoutSession` + Razorpay order notes (replace hardcoded `plan->price_yearly` / `subscription->price`).
- **T3.2** `renewSubscription(Subscription $s, float $chargedAmount): bool`:
  - `start = $s->current_period_end?->isFuture() ? $s->current_period_end : now()` (fixes G5).
  - `end = calculatePeriodEnd(start, cycle)`.
  - write `price = $chargedAmount`, `currency`, `status=ACTIVE`, `current_period_start=start`, `current_period_end=end`, clear `trial_ends_at` if past; **refresh feature snapshots** from current Plan.
- **T3.3** Upgrade proration (G10): compute charged amount = prorated difference (remaining days in old period ÷ cycle days × new plan price − old remaining value), store on `CheckoutSession`; pass to `createSubscription`. Gate by `config('subscriptions.proration_enabled')`; if disabled, charge full new-plan price (current behavior) but still create new sub via webhook.
- **T3.4** Remove `Subscription::getCurrentPrice()` (unused); ensure `profile-controller.blade.php` shows `$subscription->price` (now the correctly charged price). Audit blades for any other price source.
- **Verify:** feature test — increase plan price, then renew → `Payment.amount` = **new** price, `subscription.price` updated, period starts at `max(end, now())`; upgrade test asserts prorated (or full) charge and a single active subscription on the new plan.

### Phase 4 — Lifecycle & money hardening (G6, G7, G8)
- **T4.1** `checkExpiredSubscriptions()`: for each expiring **non-trial** active sub, set `grace_period_ends_at = now()->addDays(config('subscriptions.grace_days'))` **before** flipping to `EXPIRED`. Keep `canAccessFeatures()` grace logic intact.
- **T4.2** `RazorpayService::createOrder`: `amount` in paisa = `(int) round($data['amount'] * 100)` (fixes G7).
- **T4.3** Amount reconciliation (G8): in the completion helper (used by webhook + redirect), fetch Razorpay captured amount (`getPayment` / `verifyPaymentByOrderId`) and compare to `Payment.amount`; if `abs(diff) > tolerance` → set `Payment` to a `needs_review` state (or `failed` with metadata) and **do not dispatch provisioning**; alert log. Otherwise complete + dispatch.
- **Verify:** feature test — expired non-trial sub gets a grace window and retains access during it; paisa rounding exact for `.99` amounts; intentionally mismatched captured amount blocks provisioning and flags the payment.

### Phase 5 — Tests, static analysis, cleanup
- **T5.1** Comprehensive PHPUnit feature tests covering: new / upgrade / renewal (happy + price-changed), webhook idempotency (double delivery), new-sub-via-webhook-only, upgrade-not-misrouted, grace period, paisa rounding, amount mismatch, expired CheckoutSession. Run via `composer test:fresh:seed` (test DB only).
- **T5.2** `vendor/bin/pint --dirty` and `phpstan`/larastan at project level — zero new errors on touched files.
- **T5.3** Remove dead code: `PaymentController::handleWebhook`, `Subscription::getCurrentPrice()` if truly unused, unused `determineCheckoutType()`. Ensure `routes/web.php` no longer registers the deleted webhook; confirm Razorpay dashboard points at the single remaining endpoint.

---

## 5. Verification & Rollout

1. All migrations/seeders executed **only on `gym_app_test`** during development (`composer test:db:reset:seed`).
2. Full suite: `composer test:fresh:seed` must pass.
3. Manual smoke: (a) change a plan's yearly price in admin → start a renewal → confirm charged new price + invoice matches; (b) pay then close tab before redirect → confirm subscription still created by webhook; (c) simulate webhook retry → confirm single provisioning.
4. Production rollout: run migrations with `--env=testing`/safe scripts; keep both webhook URLs responding during a transition window by having the deprecated one forward to the new handler (or update Razorpay dashboard to the single URL before deleting the route).

---

## 6. Risks & Open Decisions

- **Proration policy (G10):** product decision required — full price vs prorated credit. Plan gates both behind `config('subscriptions.proration_enabled')`; default = prorated. *Needs your confirmation.*
- **Webhook URL cutover (G11):** Razorpay dashboard may have both URLs registered. Recommend updating to the single endpoint; during transition the old route can 301/forward. *Needs infra confirmation.*
- **Legacy `subscription->price` semantics:** after this change `price` = last charged amount (was: creation-time snapshot). Any report/export reading it should treat it as "last charged", not "current list price" (use `plan_prices` for list price). *Doc note.*
- **Double-webhook during cutover:** idempotency (`provisioned_at`) makes parallel firing safe.
