<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use App\Services\RazorpayService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\Expectation;
use Tests\TestCase;

class SubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSession(User $user, Plan $plan, array $attributes = []): CheckoutSession
    {
        return CheckoutSession::factory()->create(array_merge([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'type' => CheckoutSession::TYPE_NEW,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ], $attributes));
    }

    public function test_checkout_creates_order_and_persists_it_for_idempotent_reload(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $session = $this->makeSession($user, $plan);

        /** @var Expectation $expectation */
        $expectation = $this->mock(RazorpayService::class)->shouldReceive('createOrder');
        $expectation->once()->andReturn([
            'order_id' => 'order_abc123',
            'amount' => 100000,
            'currency' => 'INR',
            'key' => 'rzp_test_key',
            'payment_id' => 1,
        ]);

        $response = $this->actingAs($user)
            ->get(route('subscriptions.checkout', ['token' => $session->token]));

        $response->assertOk();
        $response->assertViewHas('orderId', 'order_abc123');

        // The order id must be persisted so a refresh does not hit the API again.
        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $session->id,
            'razorpay_order_id' => 'order_abc123',
        ]);

        // Second load (e.g. browser refresh) reuses the persisted order.
        $response2 = $this->actingAs($user)
            ->get(route('subscriptions.checkout', ['token' => $session->token]));

        $response2->assertOk();
        $response2->assertViewHas('orderId', 'order_abc123');
        // Mockery verifies createOrder was called exactly once at teardown.
    }

    public function test_checkout_reuses_existing_order_without_calling_razorpay(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $session = $this->makeSession($user, $plan, [
            'razorpay_order_id' => 'order_existing',
        ]);

        // Razorpay must NOT be contacted when an order already exists.
        /** @var Expectation $expectation */
        $expectation = $this->mock(RazorpayService::class)->shouldReceive('createOrder');
        $expectation->never();

        $response = $this->actingAs($user)
            ->get(route('subscriptions.checkout', ['token' => $session->token]));

        $response->assertOk();
        $response->assertViewHas('orderId', 'order_existing');
    }

    public function test_gym_owner_checkout_redirects_with_error_when_razorpay_unreachable(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();

        /** @var Expectation $expectation */
        $expectation = $this->mock(RazorpayService::class)->shouldReceive('createOrder');
        $expectation->andThrow(new Exception('cURL error 28: Connection timed out'));

        $response = $this->actingAs($user)
            ->withSession([
                'gym_owner_registration' => [
                    'selected_plan_id' => $plan->id,
                    'name' => 'Jane',
                    'email' => 'jane@example.com',
                    'gym_name' => 'Fit Gym',
                ],
            ])
            ->get(route('gym-owner.checkout'));

        $response->assertRedirect(route('gym.register'));
        $response->assertSessionHas('error');
    }
}
