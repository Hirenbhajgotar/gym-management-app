<?php

namespace Tests\Feature;

use App\Services\SubscriptionService;
use App\Services\RazorpayService;
use App\Jobs\SubscriptionRenewalJob;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionRenewalJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_renewal_job_can_be_dispatched()
    {
        Queue::fake();

        // Dispatch the job
        dispatch(new SubscriptionRenewalJob());

        // Assert that the job was pushed to the queue
        Queue::assertPushed(SubscriptionRenewalJob::class);
    }

    public function test_subscription_renewal_job_processes_expiring_subscriptions()
    {
        // Create a test user
        $user = User::factory()->create();

        // Create a subscription that expires soon (within 3 days)
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'current_period_end' => now()->addDays(2), // Expires in 2 days
            'status' => 'active',
        ]);

        // Dispatch and run the job synchronously
        $job = new SubscriptionRenewalJob;
        $job->handle(resolve(SubscriptionService::class), resolve(RazorpayService::class));

        // The job should have processed the expiring subscription
        // Since we don't have saved payment methods, it should suspend the subscription
        $subscription->refresh();
        $this->assertEquals('suspended', $subscription->status);
    }
}
