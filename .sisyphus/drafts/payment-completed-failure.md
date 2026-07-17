# Draft: PaymentCompleted failure → subscription never created

## Problem Statement
In `SubscriptionController::verifyPayment()` (line 154) the controller dispatches
`event(new PaymentCompleted($verifiedPayment->id))` and then **unconditionally**
returns a success flash + redirect to `profile` (lines 157-165). If the listener
fails, the user is told "Subscription created/upgraded/renewed successfully!" but no
subscription exists.

## Root-Cause Analysis
- `PaymentCompleted` is wired in `AppServiceProvider.php:113-116` to
  `SubscriptionProvisioner`.
- `SubscriptionProvisioner implements ShouldQueue` (line 21). In production
  `QUEUE_CONNECTION=redis` (`.env:46`) → listener runs **async on a queue worker**,
  OUTSIDE the HTTP request. Tests use `sync` (`.env.testing:12`), which is why the
  existing test (`verify_payment_redirects_to_profile...`) passes and masks the gap.
- Failure modes:
  1. Queue worker down → job never runs → `provisioned_at` stays null.
  2. Listener throws (User/Plan not found, "Upgrade context missing", deadlock
     exhaustion, DB/Redis blip) → re-throws (line 96) → after retry exhaustion the
     job lands in `failed_jobs` with `status=completed, provisioned_at=null`.
  3. No reconciliation command exists to sweep `completed AND provisioned_at IS NULL`.
  4. `provisioned_at` column (migration 2026_07_13_...) has **no index** → any
     sweep would table-scan `payments`.
- `verifyPayment()` never inspects `provisioned_at` → cannot detect failure.

## Chosen Strategy (user-confirmed)
1. **Synchronous provisioning** in `verifyPayment()`: call the provisioning logic
   BEFORE the success message, inside the existing try (or its own DB transaction),
   so success is only shown once the subscription is actually persisted.
2. **Scheduled reconciliation command** `subscriptions:reconcile-provisioning`
   that finds `status=completed AND provisioned_at IS NULL` (and the amount-mismatch
   held ones optionally) and re-runs provisioning; alert admins on unrecoverable.
3. **Add index** on `payments.provisioned_at`.
4. **Fix webhook silent-failure**: `handlePaymentWebhook` returns 200 even when the
   inner event provisioning throws (lines 71-80). Make provisioning failures surface
   (return non-2xx so Razorpay redelivers, OR rely on the shared reconciliation).

## Code Entities Involved
- `app/Http/Controllers/SubscriptionController.php::verifyPayment()` (130-175)
- `app/Listeners/SubscriptionProvisioner.php` (ShouldQueue, handle 29-98, provision 111-179)
- `app/Providers/AppServiceProvider.php:113-116` (event→listener wiring)
- `app/Http/Controllers/Api/RazorpayWebhookController.php::handlePaymentCaptured()` (87-134)
- `app/Services/SubscriptionService.php` (createSubscription/renew/cancel)
- `app/Models/Payment.php` (provisioned_at cast line 114, fillable 100)
- `database/migrations/2026_07_13_..._add_provisioned_at_to_payments_table.php`
- `routes/console.php` (scheduler, lines 12-97)
- `tests/Feature/SubscriptionPaymentRedesignTest.php` (existing tests 203-278)

## Test Infrastructure
- PHPUnit (phpunit.xml forces `gym_app_test`). Tests exist for webhook + verifyPayment.
- Test DB safety: use composer scripts / `--env=testing`. Never migrate:fresh on gym_app.

## Scope Boundaries
- IN: controller sync provisioning, reconcile command, index, webhook fix, tests.
- OUT: changing Razorpay SDK, changing queue driver, altering SubscriptionService
  business logic, touching gym_owner_registration flow provisioning.

## Open Decisions Resolved
- Keep `SubscriptionProvisioner` listener (reused by reconcile + webhook) but add a
  synchronous provisioning path callable from the controller. Extract provisioning
  into a reusable service so both sync + async + reconcile share it.
