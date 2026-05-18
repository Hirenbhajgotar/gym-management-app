<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Jobs\ProcessTwilioTemplateWebhook;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TwilioTemplateWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected WhatsAppAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->whatsappAccount = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest_twilio_sid',
            'twilio_auth_token' => 'test_twilio_auth_token',
            'messaging_service_sid' => 'MGtest_twilio_service',
            'waba_id' => 'test_waba_123',
            'status' => 'active',
            'verification_status' => 'verified',
            'business_name' => 'Test Twilio Gym',
            'business_email' => 'testtwilio@gym.com',
        ]);

        Config::set('services.twilio.webhook_verify_token', 'test_twilio_verify_token');
        Config::set('services.twilio.master_token', 'test_twilio_auth_token');
    }

    #[Test]
    public function test_verify_webhook_valid_token_returns_challenge(): void
    {
        $response = $this->get('/api/webhooks/twilio/templates-status?hub_mode=subscribe&hub_verify_token=test_twilio_verify_token&hub_challenge=challenge_12345');

        $response->assertStatus(200);
        $response->assertContent('challenge_12345');
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    #[Test]
    public function test_verify_webhook_invalid_token_returns_403(): void
    {
        $response = $this->get('/api/webhooks/twilio/templates-status?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=challenge_12345');

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Verification failed']);
    }

    #[Test]
    public function test_verify_webhook_without_hub_mode_returns_ok(): void
    {
        $response = $this->get('/api/webhooks/twilio/templates-status');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    #[Test]
    public function test_webhook_missing_required_fields_returns_422(): void
    {
        $response = $this->post('/api/webhooks/twilio/templates-status', [
            'ContentSid' => 'HXtest_sid',
            // Missing Status
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Missing required fields']);
    }

    #[Test]
    public function test_webhook_missing_content_sid_returns_422(): void
    {
        $response = $this->post('/api/webhooks/twilio/templates-status', [
            'Status' => 'approved',
            // Missing ContentSid
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Missing required fields']);
    }

    #[Test]
    public function test_webhook_valid_request_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->post('/api/webhooks/twilio/templates-status', [
            'ContentSid' => 'HXtest_template_123',
            'Status' => 'approved',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        Queue::assertPushed(ProcessTwilioTemplateWebhook::class, function ($job) {
            return $job->contentSid === 'HXtest_template_123'
                && $job->status === 'approved';
        });
    }

    #[Test]
    public function test_webhook_with_error_details_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->post('/api/webhooks/twilio/templates-status', [
            'ContentSid' => 'HXtest_rejected_template',
            'Status' => 'rejected',
            'ErrorCode' => '1234',
            'ErrorMessage' => 'Template contains prohibited content',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        Queue::assertPushed(ProcessTwilioTemplateWebhook::class, function ($job) {
            return $job->contentSid === 'HXtest_rejected_template'
                && $job->status === 'rejected'
                && $job->errorCode === '1234'
                && $job->errorMessage === 'Template contains prohibited content';
        });
    }

    #[Test]
    public function test_webhook_accepts_all_status_values(): void
    {
        Queue::fake();

        $statuses = ['approved', 'rejected', 'pending', 'disabled', 'paused'];

        foreach ($statuses as $status) {
            $response = $this->post('/api/webhooks/twilio/templates-status', [
                'ContentSid' => "HXtest_{$status}",
                'Status' => $status,
            ]);

            $response->assertStatus(200);
            $response->assertJson(['status' => 'success']);
        }

        Queue::assertPushed(ProcessTwilioTemplateWebhook::class, count($statuses));
    }

    #[Test]
    public function test_webhook_handles_case_insensitive_status(): void
    {
        Queue::fake();

        $response = $this->post('/api/webhooks/twilio/templates-status', [
            'ContentSid' => 'HXtest_case',
            'Status' => 'APPROVED',
        ]);

        $response->assertStatus(200);

        Queue::assertPushed(ProcessTwilioTemplateWebhook::class, function ($job) {
            return $job->status === 'APPROVED';
        });
    }
}
