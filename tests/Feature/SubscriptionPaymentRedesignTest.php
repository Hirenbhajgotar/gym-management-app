<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Events\PaymentCompleted;
use App\Http\Controllers\Api\RazorpayWebhookController;
use App\Livewire\Frontend\ProfileController;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PriceResolver;
use App\Services\RazorpayService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;
use Mockery;
use Mockery\Expectation;
use Mockery\ExpectationInterface;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Feature tests for the subscription / payments redesign (plan
 * subscription-payment-redesign). Targets gaps G1-G12.
 */
class SubscriptionPaymentRedesignTest extends TestCase
{
    #[Test]
    public function price_resolver_returns_current_versioned_price_and_falls_back(): void
    {
        $plan = Plan::factory()->create([
            'price_yearly' => 1000,
            'price_monthly' => 100,
            'currency' => Currency::INR,
        ]);

        // PlanObserver seeds an open plan_prices row on create.
        $this->assertEquals(1000, resolve(PriceResolver::class)->resolve($plan, 'yearly')['amount']);

        // Updating the plan price opens a new versioned row (G12) and the
        // resolver now returns the new price (G1).
        $plan->update(['price_yearly' => 1500]);
        $this->assertEquals(1500, resolve(PriceResolver::class)->resolve($plan, 'yearly')['amount']);
    }

    #[Test]
    public function plan_observer_versions_prices_on_change(): void
    {
        $plan = Plan::factory()->create(['price_yearly' => 1000]);

        $this->assertEquals(1, PlanPrice::query()->where('plan_id', $plan->id)->whereNull('effective_to')->count());

        $plan->update(['price_yearly' => 1500]);

        $this->assertEquals(2, PlanPrice::query()->where('plan_id', $plan->id)->count());
        $this->assertEquals(1, PlanPrice::query()->where('plan_id', $plan->id)->whereNull('effective_to')->count());
        $this->assertEquals(
            1500,
            PlanPrice::query()->where('plan_id', $plan->id)->whereNull('effective_to')->value('price_yearly')
        );
    }

    #[Test]
    public function renewal_snapshots_charged_price_and_starts_from_max_end_or_now(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 1000, 'currency' => Currency::INR]);
        $subscription = $this->makeSubscription($user, $plan, [
            'status' => SubscriptionStatus::ACTIVE,
            'price' => 1000,
            'current_period_end' => now()->subDay(), // lapsed
        ]);

        resolve(SubscriptionService::class)->renewSubscription($subscription, 1500.00);

        $subscription->refresh();
        // Charged amount snapshotted (G1).
        $this->assertEquals(1500.00, $subscription->price);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        // Lapsed subscription starts renewal now, not in the past (G5).
        $currentPeriodStart = $subscription->current_period_start;
        $this->assertNotNull($currentPeriodStart);
        $this->assertTrue($currentPeriodStart->greaterThanOrEqualTo(now()->subMinute()));
    }

    #[Test]
    public function renewal_of_future_subscription_starts_from_existing_end(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 1000, 'currency' => Currency::INR]);
        $futureEnd = now()->addDays(10);
        $subscription = $this->makeSubscription($user, $plan, [
            'status' => SubscriptionStatus::ACTIVE,
            'price' => 1000,
            'current_period_end' => $futureEnd,
        ]);

        resolve(SubscriptionService::class)->renewSubscription($subscription, 1000.00);

        $subscription->refresh();
        // Period continues from the existing (future) end, not reset to now (G5).
        // Microseconds are lost on the DB round-trip, so compare without them.
        $currentPeriodStart = $subscription->current_period_start;
        $this->assertNotNull($currentPeriodStart);
        $this->assertTrue(
            $currentPeriodStart->equalTo($futureEnd->copy()->setMicroseconds(0))
        );
    }

    #[Test]
    public function expired_subscriptions_get_grace_period_before_expiry(): void
    {
        config(['subscriptions.grace_days' => 7]);
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 1000, 'currency' => Currency::INR]);
        $subscription = $this->makeSubscription($user, $plan, [
            'status' => SubscriptionStatus::ACTIVE,
            'current_period_end' => now()->subDay(),
        ]);

        resolve(SubscriptionService::class)->checkExpiredSubscriptions();

        $subscription->refresh();
        // Not flipped to EXPIRED; grace period applied (G6).
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNotNull($subscription->grace_period_ends_at);
        $this->assertTrue($subscription->grace_period_ends_at->greaterThan(now()));

        // Once grace elapses, it expires.
        $subscription->update(['grace_period_ends_at' => now()->subDay()]);
        resolve(SubscriptionService::class)->checkExpiredSubscriptions();
        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::EXPIRED, $subscription->status);
    }

    #[Test]
    public function webhook_provisions_subscription_and_is_idempotent_on_redelivery(): void
    {
        config(['services.razorpay.webhook_secret' => 'test_secret']);
        config(['services.razorpay.key_id' => 'test']);
        config(['services.razorpay.key_secret' => 'test']);

        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);
        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => $plan->id,
            'billing_cycle' => 'yearly',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ]);
        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_webhook_1',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::PENDING,
            'payment_type' => 'subscription_new',
            'user_id' => $user->id,
            'metadata' => ['checkout_session_token' => $session->token, 'plan_id' => $plan->id],
        ]);

        $controller = resolve(RazorpayWebhookController::class);
        $request = $this->signedWebhookRequest([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_1',
                'order_id' => 'order_webhook_1',
                'status' => 'captured',
            ]]],
        ]);

        $controller->handlePaymentWebhook($request);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);

        // Redelivery must not create a duplicate (G3).
        $controller->handlePaymentWebhook($request);
        $this->assertEquals(1, Subscription::query()->where('user_id', $user->id)->where('plan_id', $plan->id)->count());

        $payment->refresh();
        $this->assertNotNull($payment->provisioned_at);
    }

    #[Test]
    public function verify_payment_redirects_to_profile_with_success_and_provisions_subscription(): void
    {
        // The paid flow: after Razorpay redirects the user back, verifyPayment
        // confirms the signature, dispatches PaymentCompleted, and the
        // (synchronously queued) SubscriptionProvisioner creates the
        // subscription. The user is sent back to the profile with a success
        // flash (G2/G3).
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);
        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => $plan->id,
            'billing_cycle' => 'yearly',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => ['trial_days' => 0, 'has_used_trial_before' => false],
        ]);
        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_verify_1',
            'razorpay_payment_id' => 'pay_verify_1',
            'razorpay_signature' => 'sig_verify_1',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
            'user_id' => $user->id,
            'metadata' => ['checkout_session_token' => $session->token, 'plan_id' => $plan->id],
        ]);

        // Network call to Razorpay is mocked; verification returns our Payment.
        $razorpay = Mockery::mock(RazorpayService::class);
        /** @var Expectation $expectation */
        $expectation = $razorpay->shouldReceive('verifyPayment');
        $expectation->andReturn($payment);
        app()->instance(RazorpayService::class, $razorpay);

        // Invoice PDF generation is an orthogonal side-effect (covered by the
        // webhook test) and is slow; mock it so this test focuses on the
        // redirect + provisioning behaviour.
        $invoice = Mockery::mock(InvoiceService::class);
        /** @var Expectation $expectation */
        $expectation = $invoice->shouldReceive('generateInvoice');
        $expectation->andReturnNull();
        app()->instance(InvoiceService::class, $invoice);

        // Provide a valid CSRF token so the POST reaches the controller under
        // any test environment (the app does not auto-skip CSRF outside the
        // testing env). Session/auth middleware stay enabled so flash and
        // Auth::id() still work.
        $csrf = 'test-csrf-token';
        $this->actingAs($user)
            ->withSession(['_token' => $csrf])
            ->post(route('subscriptions.verify-payment'), [
                'razorpay_order_id' => 'order_verify_1',
                'razorpay_payment_id' => 'pay_verify_1',
                'razorpay_signature' => 'sig_verify_1',
                '_token' => $csrf,
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success', 'Subscription created successfully!');

        // Subscription was provisioned for the user/plan.
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $payment->refresh();
        $this->assertNotNull($payment->provisioned_at);
    }

    #[Test]
    public function amount_mismatch_holds_payment_for_review_without_provisioning(): void
    {
        config(['subscriptions.amount_mismatch_tolerance' => 0]);

        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);
        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => $plan->id,
            'billing_cycle' => 'yearly',
            'amount' => 1000,
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ]);
        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_mismatch_1',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
            'user_id' => $user->id,
            'metadata' => ['checkout_session_token' => $session->token, 'plan_id' => $plan->id],
        ]);

        event(new PaymentCompleted($payment->id));

        $this->assertDatabaseMissing('subscriptions', ['user_id' => $user->id]);
        $payment->refresh();
        $this->assertTrue($payment->metadata['amount_mismatch'] ?? false);
        $this->assertNull($payment->provisioned_at);
    }

    #[Test]
    public function expired_checkout_session_returns_404(): void
    {
        $user = User::factory()->create();
        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => Plan::factory()->create()->id,
            'billing_cycle' => 'yearly',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_EXPIRED,
            'expires_at' => now()->subMinutes(10),
            'metadata' => [],
        ]);

        $this->actingAs($user)
            ->get(route('subscriptions.checkout', ['token' => $session->token]))->assertNotFound();
    }

    #[Test]
    public function razorpay_order_uses_rounded_paisa_amount(): void
    {
        // 999.99 INR must become 99999 paisa, not 99998 (G7). The rounding is
        // encapsulated in the protected toPaisa() helper so it can be unit
        // tested without a Razorpay API call.
        $service = resolve(RazorpayService::class);

        $method = new ReflectionMethod($service, 'toPaisa');
        $method->setAccessible(true);

        $this->assertEquals(99999, $method->invoke($service, 999.99));
        $this->assertEquals(100000, $method->invoke($service, 1000.0));
    }

    #[Test]
    public function checkout_token_resolves_session_and_renders_order(): void
    {
        // The checkout page is token-based: a valid PENDING session token
        // returns 200 (the session is resolved by token, not fragile session
        // keys) (G9). The Razorpay API call is mocked to avoid network use.
        $razorpay = Mockery::mock(RazorpayService::class);
        $expectation = $razorpay->shouldReceive('createOrder');
        assert($expectation instanceof ExpectationInterface);
        $expectation->andReturn([
            'order_id' => 'order_test_123',
            'amount' => 200000,
            'currency' => 'INR',
        ]);
        app()->instance(RazorpayService::class, $razorpay);

        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);
        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => $plan->id,
            'billing_cycle' => 'yearly',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ]);

        $this->actingAs($user)
            ->get(route('subscriptions.checkout', ['token' => $session->token]))
            ->assertOk();

        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $session->id,
            'status' => CheckoutSession::STATUS_PENDING,
        ]);
    }

    #[Test]
    public function profile_select_plan_creates_checkout_session_and_redirects(): void
    {
        // ProfileController now persists a CheckoutSession and redirects with
        // its token instead of relying on fragile session keys (G9).
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->call('selectPlan', $plan->id)
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_sessions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'type' => CheckoutSession::TYPE_NEW,
            'status' => CheckoutSession::STATUS_PENDING,
        ]);
    }

    #[Test]
    public function profile_upgrade_plan_creates_prorated_session(): void
    {
        // Upgrading charges only the prorated difference (G4/G10), and routes
        // through a checkout session of type upgrade rather than a renewal.
        config(['subscriptions.proration_enabled' => true]);

        $user = User::factory()->create();
        $currentPlan = Plan::factory()->create(['price_yearly' => 1000, 'currency' => Currency::INR]);
        $newPlan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);
        $this->makeSubscription($user, $currentPlan, [
            'billing_cycle' => 'yearly',
            'price' => 1000,
            'current_period_end' => now()->addYear(),
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->call('upgradePlan', $newPlan->id)
            ->assertRedirect();

        $session = CheckoutSession::query()
            ->where('user_id', $user->id)
            ->where('type', CheckoutSession::TYPE_UPGRADE)
            ->first();

        $this->assertNotNull($session);
        // Proration credits the unused portion, so the charged amount is less
        // than the full new-plan price.
        $this->assertLessThan(2000, (float) $session->amount);
        $this->assertGreaterThan(0, (float) $session->amount);
    }

    #[Test]
    public function verify_payment_shows_error_not_success_when_provisioning_fails(): void
    {
        // The core bug: previously verifyPayment() fired the event and showed
        // success regardless of whether the subscription was actually created.
        // Now provisioning runs synchronously, so a failure must surface as an
        // error flash and no subscription must exist.
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);
        CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => $plan->id,
            'billing_cycle' => 'yearly',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => ['trial_days' => 0, 'has_used_trial_before' => false],
        ]);
        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_verify_fail_1',
            'razorpay_payment_id' => 'pay_verify_fail_1',
            'razorpay_signature' => 'sig_verify_fail_1',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
            'user_id' => $user->id,
            // No checkout_session_token, so resolveSession() returns null and the
            // fallback path looks up plan_id 999999 (does not exist) -> the
            // service throws "Plan not found", which the controller must catch.
            'metadata' => ['plan_id' => 999999],
        ]);

        // The payment references a non-existent plan (999999), so the shared
        // provisioning service throws "Plan not found" inside the transaction.
        // The controller's catch must convert that into an error flash and the
        // success flash must never be set.
        $razorpay = Mockery::mock(RazorpayService::class);
        /** @var Expectation $expectation */
        $expectation = $razorpay->shouldReceive('verifyPayment');
        $expectation->andReturn($payment);

        app()->instance(RazorpayService::class, $razorpay);

        $csrf = 'test-csrf-token';
        $this->actingAs($user)
            ->withSession(['_token' => $csrf])
            ->post(route('subscriptions.verify-payment'), [
                'razorpay_order_id' => 'order_verify_fail_1',
                'razorpay_payment_id' => 'pay_verify_fail_1',
                'razorpay_signature' => 'sig_verify_fail_1',
                '_token' => $csrf,
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('error', 'Payment failed. Please try again.')
            ->assertSessionMissing('success');

        // No subscription was created despite the "completed" payment.
        $this->assertDatabaseMissing('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
    }

    #[Test]
    public function reconcile_command_provisions_orphaned_completed_payment(): void
    {
        // A completed subscription payment that was never provisioned (e.g. the
        // queued listener failed) must be reconciled by the scheduled command.
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);
        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => $plan->id,
            'billing_cycle' => 'yearly',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ]);
        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_orphan_1',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
            'user_id' => $user->id,
            'provisioned_at' => null,
            'metadata' => ['checkout_session_token' => $session->token, 'plan_id' => $plan->id],
        ]);

        $this->assertEquals(0, Subscription::query()->where('user_id', $user->id)->count());

        // $this->artisan(ReconcileSubscriptionsCommand::class)->assertSuccessful();
        $this->runCommand('app:reconcile-subscriptions')->assertSuccessful();

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);
        $this->assertNotNull($payment->refresh()->provisioned_at);
    }

    #[Test]
    public function reconcile_command_dry_run_makes_no_writes(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);
        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => $plan->id,
            'billing_cycle' => 'yearly',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ]);
        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_dryrun_1',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
            'user_id' => $user->id,
            'provisioned_at' => null,
            'metadata' => ['checkout_session_token' => $session->token, 'plan_id' => $plan->id],
        ]);

        // $this->artisan(ReconcileSubscriptionsCommand::class, ['--dry-run' => true])->assertSuccessful();
        $this->runCommand('app:reconcile-subscriptions --dry-run')->assertSuccessful();

        // Nothing was provisioned.
        $this->assertDatabaseMissing('subscriptions', ['user_id' => $user->id]);
        $this->assertNull($payment->refresh()->provisioned_at);
    }

    #[Test]
    public function webhook_and_reconcile_double_fire_creates_single_subscription(): void
    {
        // The webhook provisions via the queued listener (sync in tests) and
        // the reconcile command later sweeps the same payment. Idempotency must
        // guarantee exactly one subscription.
        config(['services.razorpay.webhook_secret' => 'test_secret']);
        config(['services.razorpay.key_id' => 'test']);
        config(['services.razorpay.key_secret' => 'test']);

        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);
        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => $plan->id,
            'billing_cycle' => 'yearly',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ]);
        Payment::query()->create([
            'razorpay_order_id' => 'order_double_1',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::PENDING,
            'payment_type' => 'subscription_new',
            'user_id' => $user->id,
            'metadata' => ['checkout_session_token' => $session->token, 'plan_id' => $plan->id],
        ]);

        $controller = resolve(RazorpayWebhookController::class);
        $request = $this->signedWebhookRequest([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_double_1',
                'order_id' => 'order_double_1',
                'status' => 'captured',
            ]]],
        ]);

        $controller->handlePaymentWebhook($request);

        // Now reconcile (simulating the safety-net sweep on a later run).
        // $this->artisan(ReconcileSubscriptionsCommand::class)->assertSuccessful();
        $this->runCommand('app:reconcile-subscriptions')->assertSuccessful();

        $this->assertEquals(
            1,
            Subscription::query()->where('user_id', $user->id)->where('plan_id', $plan->id)->count()
        );
    }

    #[Test]
    public function reconcile_command_skips_amount_mismatch_payments(): void
    {
        // A payment held for manual review (amount_mismatch) must NOT be
        // provisioned by reconcile.
        config(['subscriptions.amount_mismatch_tolerance' => 0]);

        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price_yearly' => 2000, 'currency' => Currency::INR]);
        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => $plan->id,
            'billing_cycle' => 'yearly',
            'amount' => 1000, // mismatch vs captured 2000
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ]);
        $payment = Payment::query()->create([
            'razorpay_order_id' => 'order_mismatch_reconcile_1',
            'amount' => 2000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription_new',
            'user_id' => $user->id,
            'provisioned_at' => null,
            'metadata' => ['checkout_session_token' => $session->token, 'plan_id' => $plan->id],
        ]);

        // $this->artisan(ReconcileSubscriptionsCommand::class)->assertSuccessful();
        $this->runCommand('app:reconcile-subscriptions')->assertSuccessful();

        $this->assertDatabaseMissing('subscriptions', ['user_id' => $user->id]);
        // The mismatch flag must be set by the service's hold logic.
        $this->assertTrue($payment->refresh()->metadata['amount_mismatch'] ?? false);
        $this->assertNull($payment->refresh()->provisioned_at);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSubscription(User $user, Plan $plan, array $attributes = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'billing_cycle' => 'yearly',
            'price' => 1000,
            'currency' => Currency::INR,
            'current_period_start' => now()->subYear(),
            'current_period_end' => now()->addYear(),
            'metadata' => [],
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedWebhookRequest(array $payload): Request
    {
        $body = (string) json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test_secret');
        $request = Request::create('/api/razorpay/payment', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Razorpay-Signature', $signature);

        return $request;
    }

    private function runCommand(string $command): PendingCommand
    {
        $result = $this->artisan($command);
        assert($result instanceof PendingCommand);

        return $result;
    }
}
