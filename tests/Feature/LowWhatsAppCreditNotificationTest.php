<?php

namespace Tests\Feature;

use App\Jobs\SendLowWhatsAppCreditNotificationJob;
use App\Models\Gym;
use App\Models\User;
use App\Models\WhatsAppMessageCredit;
use App\Services\WhatsAppBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LowWhatsAppCreditNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_low_credits_queues_job_when_balance_is_low()
    {
        Queue::fake();

        $gym = Gym::factory()->create();
        $owner = User::factory()->gymOwner()->create();
        $gym->users()->attach($owner->id, ['role' => \App\Enums\DefaultRole::GYM_OWNER->value, 'active' => true]);

        $credit = WhatsAppMessageCredit::create([
            'gym_id' => $gym->id,
            'total_credits' => 100,
            'used_credits' => 80,
            'remaining_credits' => 20, // 20 * 0.25 = $5.00
            'cost_per_message' => 0.15,
            'selling_price_per_msg' => 0.25,
            'is_low_credit_notified' => false,
        ]);

        Artisan::call('whatsapp:check-low-credits');

        Queue::assertPushed(SendLowWhatsAppCreditNotificationJob::class, function ($job) use ($credit) {
            return $job->credit->id === $credit->id;
        });
    }

    public function test_check_low_credits_does_not_queue_job_when_balance_is_over_5()
    {
        Queue::fake();

        $gym = Gym::factory()->create();
        $owner = User::factory()->gymOwner()->create();
        $gym->users()->attach($owner->id, ['role' => \App\Enums\DefaultRole::GYM_OWNER->value, 'active' => true]);

        $credit = WhatsAppMessageCredit::create([
            'gym_id' => $gym->id,
            'total_credits' => 100,
            'used_credits' => 70,
            'remaining_credits' => 30, // 30 * 0.20 = $6.00
            'cost_per_message' => 0.15,
            'selling_price_per_msg' => 0.20,
            'is_low_credit_notified' => false,
        ]);

        Artisan::call('whatsapp:check-low-credits');

        Queue::assertNotPushed(SendLowWhatsAppCreditNotificationJob::class);
    }

    public function test_check_low_credits_skips_gyms_already_notified()
    {
        Queue::fake();

        $gym = Gym::factory()->create();
        $owner = User::factory()->gymOwner()->create();
        $gym->users()->attach($owner->id, ['role' => \App\Enums\DefaultRole::GYM_OWNER->value, 'active' => true]);

        $credit = WhatsAppMessageCredit::create([
            'gym_id' => $gym->id,
            'total_credits' => 100,
            'used_credits' => 90,
            'remaining_credits' => 10, // 10 * 0.25 = $2.50
            'cost_per_message' => 0.15,
            'selling_price_per_msg' => 0.25,
            'is_low_credit_notified' => true,
        ]);

        Artisan::call('whatsapp:check-low-credits');

        Queue::assertNotPushed(SendLowWhatsAppCreditNotificationJob::class);
    }

    public function test_is_low_credit_notified_flag_is_correctly_reset_upon_manual_adjustment()
    {
        $gym = Gym::factory()->create();

        $credit = WhatsAppMessageCredit::create([
            'gym_id' => $gym->id,
            'total_credits' => 10,
            'used_credits' => 0,
            'remaining_credits' => 10,
            'cost_per_message' => 0.15,
            'selling_price_per_msg' => 0.25,
            'is_low_credit_notified' => true,
        ]);

        // Add credits via service
        $service = app(WhatsAppBillingService::class);
        $service->adjustCredits($gym->id, 100, 'Manual Adjustment');

        $credit->refresh();

        // 110 credits * 0.25 = $27.50 (> $5.00) so it should reset
        $this->assertFalse($credit->is_low_credit_notified);
    }
}
