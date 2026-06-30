<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Jobs\ProcessMetaTemplateWebhook;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppTemplateWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected WhatsAppAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->whatsappAccount = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'waba_id' => 'test_waba_123',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        Config::set('services.meta.webhook_verify_token', 'test_verify_token');
        Config::set('services.meta.app_secret', 'test_app_secret');
    }

    #[Test]
    public function test_verify_webhook_valid_token_returns_challenge(): void
    {
        $response = $this->get('/api/webhooks/whatsapp/templates?hub_mode=subscribe&hub_verify_token=test_verify_token&hub_challenge=12345');

        $response->assertOk();
        $response->assertContent('12345');
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    #[Test]
    public function test_verify_webhook_invalid_token_returns_403(): void
    {
        $response = $this->get('/api/webhooks/whatsapp/templates?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=12345');

        $response->assertForbidden();
        $response->assertJson(['error' => 'Verification failed']);
    }

    #[Test]
    public function test_verify_webhook_missing_params_returns_403(): void
    {
        $response = $this->get('/api/webhooks/whatsapp/templates');

        $response->assertForbidden();
    }

    #[Test]
    public function test_webhook_invalid_signature_returns_401(): void
    {
        $response = $this->postJson('/api/webhooks/whatsapp/templates', [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'message_template_status_update',
                            'value' => [
                                'event' => 'APPROVED',
                                'message_template_id' => '123456',
                            ],
                        ],
                    ],
                ],
            ],
        ], ['X-Hub-Signature-256' => 'sha256=invalid']);

        $response->assertUnauthorized();
        $response->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function test_webhook_without_signature_returns_401(): void
    {
        $response = $this->postJson('/api/webhooks/whatsapp/templates', [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'message_template_status_update',
                            'value' => [
                                'event' => 'APPROVED',
                                'message_template_id' => '123456',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertUnauthorized();
        $response->assertJson(['error' => 'Missing signature']);
    }

    #[Test]
    public function test_webhook_valid_signature_dispatches_job(): void
    {
        Queue::fake();

        $payload = json_encode([
            'entry' => [
                [
                    'id' => 'test_waba_123',
                    'changes' => [
                        [
                            'field' => 'message_template_status_update',
                            'value' => [
                                'event' => 'APPROVED',
                                'message_template_id' => 'test_template_123',
                                'message_template_name' => 'test_template',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $signature = $this->computeSignature($payload, 'test_app_secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/whatsapp/templates',
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => strlen($payload),
            ],
            $payload
        );

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        Queue::assertPushed(ProcessMetaTemplateWebhook::class, function ($job) {
            return $job->metaTemplateId === 'test_template_123'
                && $job->event === 'APPROVED';
        });
    }

    #[Test]
    public function test_webhook_handles_multiple_template_updates(): void
    {
        Queue::fake();

        $payload = json_encode([
            'entry' => [
                [
                    'id' => 'test_waba_123',
                    'changes' => [
                        [
                            'field' => 'message_template_status_update',
                            'value' => [
                                'event' => 'APPROVED',
                                'message_template_id' => 'template_1',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'test_waba_456',
                    'changes' => [
                        [
                            'field' => 'message_template_status_update',
                            'value' => [
                                'event' => 'REJECTED',
                                'message_template_id' => 'template_2',
                                'reason' => 'Policy violation',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $signature = $this->computeSignature($payload, 'test_app_secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/whatsapp/templates',
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => strlen($payload),
            ],
            $payload
        );

        $response->assertOk();

        Queue::assertPushed(ProcessMetaTemplateWebhook::class, 2);
    }

    #[Test]
    public function test_webhook_ignores_non_template_status_changes(): void
    {
        Queue::fake();

        $payload = json_encode([
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'some_other_field',
                            'value' => [
                                'event' => 'APPROVED',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $signature = $this->computeSignature($payload, 'test_app_secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/whatsapp/templates',
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => strlen($payload),
            ],
            $payload
        );

        $response->assertOk();

        Queue::assertNotPushed(ProcessMetaTemplateWebhook::class);
    }

    private function computeSignature(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }
}
