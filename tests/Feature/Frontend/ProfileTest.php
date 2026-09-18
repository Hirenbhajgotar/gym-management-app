<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Frontend\ProfileController;
use App\Models\CheckoutSession;
use App\Models\Gym;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    #[Test]
    public function profile_page_renders_successfully_for_authenticated_user(): void
    {
        $uniqueId = uniqid();
        $user = User::factory()->create([
            'name' => 'John GymOwner ' . $uniqueId,
            'email' => 'john_' . $uniqueId . '@fluxfitness.test',
        ]);

        $gym = Gym::factory()
            ->withCheckInQrCodeToken('sample_token_32_characters_' . $uniqueId)
            ->create([
                'name' => 'Downtown Fitness ' . $uniqueId,
            ]);
        $user->gyms()->attach($gym->id, ['role' => 'gym_owner', 'active' => true]);

        $plan = Plan::factory()->create([
            'name' => 'Pro Tier ' . $uniqueId,
            'price_yearly' => 12000,
            'price_monthly' => 1200,
            'is_active' => true,
            'is_enterprise' => false,
        ]);

        $this->createSubscription($user, $plan, [
            'status' => SubscriptionStatus::ACTIVE,
            'billing_cycle' => 'yearly',
            'price' => 12000,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonths(11),
        ]);

        $this->createPayment($user, [
            'gym_id' => $gym->id,
            'amount' => 12000,
            'status' => PaymentStatus::COMPLETED,
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->assertStatus(200)
            ->assertSee('John GymOwner ' . $uniqueId)
            ->assertSee('Downtown Fitness ' . $uniqueId)
            ->assertSee('Pro Tier ' . $uniqueId);
    }

    #[Test]
    public function user_can_update_profile_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'phone' => '1111111111',
        ]);

        $this->actingAs($user);

        $testable = Livewire::test(ProfileController::class)
            ->set('name', 'Updated Name')
            ->set('phone', '+91 99999 88888')
            ->call('updateProfile');

        $testable->assertHasNoErrors();
        $testable->assertDispatched('profile-saved');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('+91 99999 88888', $user->phone);
    }

    #[Test]
    public function user_cannot_update_profile_with_invalid_name(): void
    {
        $user = User::factory()->create(['name' => 'Valid Name']);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->set('name', '')
            ->call('updateProfile')
            ->assertHasErrors(['name' => 'required']);

        $user->refresh();
        $this->assertSame('Valid Name', $user->name);
    }

    #[Test]
    public function user_can_update_password_and_invalidates_other_devices(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old_secret_password'),
        ]);

        $this->actingAs($user);

        $testable = Livewire::test(ProfileController::class)
            ->set('current_password', 'old_secret_password')
            ->set('password', 'new_secret_password_123')
            ->set('password_confirmation', 'new_secret_password_123')
            ->call('updatePassword');

        $testable->assertHasNoErrors();
        $testable->assertDispatched('password-saved');

        $user->refresh();
        $this->assertTrue(Hash::check('new_secret_password_123', $user->password));
    }

    #[Test]
    public function user_cannot_update_password_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('real_password'),
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->set('current_password', 'wrong_password')
            ->set('password', 'new_valid_password')
            ->set('password_confirmation', 'new_valid_password')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);
    }

    #[Test]
    public function user_can_start_free_trial_via_select_plan(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'name' => 'Growth Plan',
            'trial_days' => 14,
            'is_active' => true,
            'is_enterprise' => false,
        ]);

        /** @var MockInterface&SubscriptionService $mockSubscriptionService */
        $mockSubscriptionService = Mockery::mock(SubscriptionService::class);
        /** @var Expectation $expectation */
        $expectation = $mockSubscriptionService->shouldReceive('createSubscription');
        $expectation->once()
            ->with($user, Mockery::type(Plan::class), Mockery::subset([
                'billing_cycle' => 'yearly',
                'trial_days' => 14,
            ]));

        $this->app->instance(SubscriptionService::class, $mockSubscriptionService);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->call('selectPlan', $plan->id, 'yearly')
            ->assertRedirect(route('profile'));
    }

    #[Test]
    public function select_plan_creates_checkout_session_for_paid_plan(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'price_yearly' => 5000,
            'trial_days' => 0,
            'is_active' => true,
            'is_enterprise' => false,
            'currency' => Currency::INR,
        ]);

        // Ensure user has used trial
        $this->createSubscription($user, $plan, [
            'status' => SubscriptionStatus::EXPIRED,
            'trial_ends_at' => now()->subMonth(),
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->call('selectPlan', $plan->id, 'yearly');

        $this->assertDatabaseHas('checkout_sessions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'type' => CheckoutSession::TYPE_NEW,
            'billing_cycle' => 'yearly',
            'amount' => 5000,
        ]);
    }

    #[Test]
    public function select_plan_rejects_inactive_or_enterprise_plans(): void
    {
        $user = User::factory()->create();
        $inactivePlan = Plan::factory()->create([
            'is_active' => false,
            'is_enterprise' => false,
        ]);
        $enterprisePlan = Plan::factory()->create([
            'is_active' => true,
            'is_enterprise' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->call('selectPlan', $inactivePlan->id, 'yearly')
            ->assertSee('The selected plan is not available for self-service subscription.');

        Livewire::test(ProfileController::class)
            ->call('selectPlan', $enterprisePlan->id, 'yearly')
            ->assertSee('The selected plan is not available for self-service subscription.');
    }

    #[Test]
    public function select_plan_surfaces_sanitized_error_when_service_fails(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'name' => 'Starter Plan',
            'trial_days' => 7,
            'is_active' => true,
            'is_enterprise' => false,
        ]);

        /** @var MockInterface&SubscriptionService $mockSubscriptionService */
        $mockSubscriptionService = Mockery::mock(SubscriptionService::class);
        /** @var Expectation $expectation */
        $expectation = $mockSubscriptionService->shouldReceive('createSubscription');
        $expectation->once()
            ->andThrow(new Exception('Payment provider internal secret sql error'));

        $this->app->instance(SubscriptionService::class, $mockSubscriptionService);

        $this->actingAs($user);

        $testable = Livewire::test(ProfileController::class)
            ->call('selectPlan', $plan->id, 'yearly');

        $testable->assertDontSee('Payment provider internal secret sql error');
        $testable->assertSee('Unable to start your trial at this time. Please try again or contact support.');
    }

    #[Test]
    public function user_can_upgrade_plan_and_creates_checkout_session(): void
    {
        config()->set('subscriptions.proration_enabled', true);

        $user = User::factory()->create();
        $oldPlan = Plan::factory()->create([
            'price_yearly' => 6000,
            'price_monthly' => 600,
            'is_active' => true,
            'is_enterprise' => false,
            'currency' => Currency::INR,
        ]);
        $newPlan = Plan::factory()->create([
            'price_yearly' => 12000,
            'price_monthly' => 1200,
            'is_active' => true,
            'is_enterprise' => false,
            'currency' => Currency::INR,
        ]);

        $this->createSubscription($user, $oldPlan, [
            'status' => SubscriptionStatus::ACTIVE,
            'billing_cycle' => 'yearly',
            'price' => 6000,
            'current_period_start' => now()->subMonths(6),
            'current_period_end' => now()->addMonths(6),
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->call('upgradePlan', $newPlan->id);

        $session = CheckoutSession::query()->where('user_id', $user->id)
            ->where('type', CheckoutSession::TYPE_UPGRADE)
            ->latest('id')
            ->first();

        $this->assertNotNull($session);
        $this->assertSame($newPlan->id, $session->plan_id);
        $this->assertGreaterThan(0, $session->amount);
    }

    #[Test]
    public function upgrade_plan_fails_gracefully_when_no_active_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'is_active' => true,
            'is_enterprise' => false,
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->call('upgradePlan', $plan->id)
            ->assertSee('You need an active subscription to upgrade.');
    }

    #[Test]
    public function user_can_renew_plan_with_current_resolved_price(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'price_yearly' => 8000,
            'is_active' => true,
            'is_enterprise' => false,
            'currency' => Currency::INR,
        ]);

        $this->createSubscription($user, $plan, [
            'status' => SubscriptionStatus::ACTIVE,
            'billing_cycle' => 'yearly',
            'price' => 5000, // old stale price
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->call('renewPlan');

        $session = CheckoutSession::query()->where('user_id', $user->id)
            ->where('type', CheckoutSession::TYPE_RENEWAL)
            ->latest('id')
            ->first();

        $this->assertNotNull($session);
        $this->assertEquals(8000, $session->amount);
    }

    #[Test]
    public function user_can_download_their_own_invoice(): void
    {
        $user = User::factory()->create();
        $payment = $this->createPayment($user, [
            'status' => PaymentStatus::COMPLETED,
        ]);

        /** @var MockInterface&InvoiceService $mockInvoiceService */
        $mockInvoiceService = Mockery::mock(InvoiceService::class);
        /** @var Expectation $expectation */
        $expectation = $mockInvoiceService->shouldReceive('downloadInvoice');
        $expectation->once()
            ->with(Mockery::on(fn (Payment $p): bool => $p->id === $payment->id))
            ->andReturn(new StreamedResponse(function (): void {
                echo 'PDF_CONTENT';
            }));

        $this->app->instance(InvoiceService::class, $mockInvoiceService);

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->call('downloadInvoice', $payment->id);
    }

    #[Test]
    public function user_cannot_download_another_users_invoice(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $paymentOfUser2 = $this->createPayment($user2, [
            'status' => PaymentStatus::COMPLETED,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->actingAs($user1);

        Livewire::test(ProfileController::class)
            ->call('downloadInvoice', $paymentOfUser2->id);
    }

    #[Test]
    public function gym_model_uses_timing_safe_hash_equals_and_generates_svg_qr(): void
    {
        $uniqueId = uniqid();
        $gym = Gym::factory()
            ->withCheckInQrCodeToken('valid_checkin_token_' . $uniqueId)
            ->withCheckoutQrCodeToken('valid_checkout_token_' . $uniqueId)
            ->create();

        $this->assertTrue($gym->hasValidCheckInQrCodeToken('valid_checkin_token_' . $uniqueId));
        $this->assertFalse($gym->hasValidCheckInQrCodeToken('wrong_token'));

        $this->assertTrue($gym->hasValidCheckoutQrCodeToken('valid_checkout_token_' . $uniqueId));
        $this->assertFalse($gym->hasValidCheckoutQrCodeToken('wrong_token'));

        $expectedCheckinUrl = route('attendance.qr.checkin', [
            'gymId' => $gym->id,
            'token' => 'valid_checkin_token_' . $uniqueId,
        ]);
        $this->assertSame($expectedCheckinUrl, $gym->getCheckInQrCodeUrl());

        $svg = $gym->getCheckInQrCodeSvg(200);
        $this->assertNotNull($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringNotContainsString('filament', strtolower($svg));
    }

    #[Test]
    public function gym_tokens_are_guarded_from_mass_assignment(): void
    {
        $gym = new Gym();
        $gym->fill([
            'name' => 'Mass Assignment Test Gym',
            'qr_code_token' => 'malicious_injected_token',
            'qr_code_checkout_token' => 'malicious_injected_checkout',
        ]);

        $this->assertSame('Mass Assignment Test Gym', $gym->name);
        $this->assertNull($gym->qr_code_token);
        $this->assertNull($gym->qr_code_checkout_token);
    }

    #[Test]
    public function user_can_logout_via_profile_controller(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(ProfileController::class)
            ->call('logout')
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    /**
     * Helper to create a Subscription record
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createSubscription(User $user, Plan $plan, array $attributes = []): Subscription
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
     * Helper to create a Payment record
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createPayment(User $user, array $attributes = []): Payment
    {
        return Payment::query()->create(array_merge([
            'user_id' => $user->id,
            'razorpay_order_id' => 'order_' . uniqid(),
            'razorpay_payment_id' => 'pay_' . uniqid(),
            'razorpay_signature' => 'sig_' . uniqid(),
            'amount' => 1000,
            'currency' => Currency::INR,
            'status' => PaymentStatus::COMPLETED,
            'payment_type' => 'subscription',
            'paid_at' => now(),
            'metadata' => ['plan_name' => 'Test Plan'],
        ], $attributes));
    }
}
