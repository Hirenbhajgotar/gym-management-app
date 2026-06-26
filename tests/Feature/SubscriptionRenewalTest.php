<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Tests\TestCase;

class SubscriptionRenewalTest extends TestCase
{
    public function test_subscription_checkout_requires_authentication()
    {
        // Try to access checkout without authentication
        $response = $this->get(route('subscriptions.checkout'));

        // Should redirect to login
        $response->assertRedirect(route('login'));
    }

    public function test_subscription_renewal_flow_with_existing_data()
    {
        // Use existing user and subscription from the database
        $user = User::find(1978); // From our earlier check
        $subscription = Subscription::find(1); // From our earlier check

        $this->assertNotNull($user, 'User should exist');
        $this->assertNotNull($subscription, 'Subscription should exist');

        // Act as the user
        $this->actingAs($user);

        // Simulate the renewal process by setting session data
        session([
            'renew_plan_id' => $subscription->plan_id,
            'renew_subscription_id' => $subscription->id,
        ]);

        // Visit the checkout page
        $response = $this->get(route('subscriptions.checkout'));

        // Assert that the checkout page loads successfully
        $response->assertOk();

        // Assert that the view has the correct data
        $response->assertViewHas('subscription');
        $response->assertViewHas('plan');
        $response->assertViewHas('checkoutType', 'renewal');
    }
}
