<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\WhatsApp;

use App\Enums\WabaSetupSessionStatusEnum;
use App\Jobs\ImportWhatsAppTemplatesJob;
use App\Models\Gym;
use App\Models\WabaSetupSession;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupTestCase;

/**
 * Feature tests for App\Http\Controllers\WhatsApp\EmbeddedSignupController.
 *
 * Covers the two reviewed defects:
 *  - M2: the OAuth callback must reject a state that does not belong to the
 *        tenant stored in the session (cross-gym / forged-state protection).
 *  - M3: manualConnect() must redirect to the WABA account step (3), NOT the
 *        templates step (4), because template import runs asynchronously.
 */
class EmbeddedSignupControllerTest extends WhatsAppSetupTestCase
{
    #[Test]
    public function callback_rejects_state_that_belongs_to_a_different_gym(): void
    {
        Queue::fake();

        $ownerGym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();

        // Session stored for the requesting tenant (ownerGym).
        $this->seedEmbeddedSignupSession($ownerGym);

        // But the OAuth state belongs to a different gym entirely.
        $foreignSession = WabaSetupSession::createForGym($otherGym->id);

        $this->actingAsGymOwner();

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'valid-code',
            'state' => $foreignSession->session_id,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'setup_step=whats-app-business',
            $response->headers->get('Location') ?? ''
        );
        $response->assertSessionHas('error', 'Invalid OAuth state');

        // The foreign session must not be marked completed / consumed.
        $this->assertDatabaseHas('waba_setup_sessions', [
            'id' => $foreignSession->id,
            'status' => WabaSetupSessionStatusEnum::PENDING->value,
        ]);
    }

    #[Test]
    public function callback_accepts_state_that_matches_the_session_tenant(): void
    {
        Queue::fake();

        // Fake the Meta Graph API (token exchange + debug_token + waba details + phone numbers)
        Http::fake([
            '*oauth/access_token*' => Http::response([
                'access_token' => 'EAABTESTTOKEN',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*debug_token*' => Http::response([
                'data' => [
                    'user_id' => '123',
                    'app_id' => '456',
                    'granular_scopes' => [
                        [
                            'scope' => 'whatsapp_business_management',
                            'target_ids' => ['WABA1234567890'],
                        ],
                    ],
                    'scopes' => [],
                ],
            ], 200),
            '*phone_numbers*' => Http::response([
                'data' => [
                    [
                        'id' => 'PHONE123',
                        'display_phone_number' => '+1234567890',
                        'status' => 'CONNECTED',
                    ],
                ],
            ], 200),
            '*WABA1234567890*' => Http::response([
                'id' => 'WABA1234567890',
                'name' => 'Test WABA',
                'status' => 'APPROVED',
                'account_review_status' => 'APPROVED',
                'business_verification_status' => 'verified',
            ], 200),
            '*PHONE123*' => Http::response([
                'id' => 'PHONE123',
                'display_phone_number' => '+1234567890',
                'verified_name' => 'Test Business',
                'status' => 'CONNECTED',
            ], 200),
        ]);

        $gym = Gym::factory()->create();

        $this->seedEmbeddedSignupSession($gym);

        $validSession = WabaSetupSession::createForGym($gym->id);

        $this->actingAsGymOwner();

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'valid-code',
            'state' => $validSession->session_id,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('setup_step=whats-app-business', $response->headers->get('Location') ?? '');
        $response->assertSessionHas('success', 'WABA connected successfully');
    }

    #[Test]
    public function callback_rejects_an_already_consumed_state(): void
    {
        Queue::fake();

        $gym = Gym::factory()->create();

        $this->seedEmbeddedSignupSession($gym);

        // A state that has already been used to complete signup must not be
        // replayable (OAuth state-replay protection, D2).
        $usedSession = WabaSetupSession::createForGym($gym->id);
        $usedSession->markAsCompleted(['waba_id' => 'WABA1234567890']);

        $this->actingAsGymOwner();

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'valid-code',
            'state' => $usedSession->session_id,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('setup_step=whats-app-business', $response->headers->get('Location') ?? '');
        $response->assertSessionHas('error', 'Invalid OAuth state');

        // Session stays completed — it is never re-activated.
        $this->assertDatabaseHas('waba_setup_sessions', [
            'id' => $usedSession->id,
            'status' => WabaSetupSessionStatusEnum::COMPLETED->value,
        ]);
    }

    #[Test]
    public function callback_rejects_an_expired_state(): void
    {
        Queue::fake();

        $gym = Gym::factory()->create();

        $this->seedEmbeddedSignupSession($gym);

        $expiredSession = WabaSetupSession::createForGym($gym->id);
        $expiredSession->update(['expires_at' => now()->subHour()]);

        $this->actingAsGymOwner();

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'valid-code',
            'state' => $expiredSession->session_id,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('setup_step=whats-app-business', $response->headers->get('Location') ?? '');
        $response->assertSessionHas('error', 'Invalid OAuth state');
    }

    #[Test]
    public function manual_connect_redirects_to_account_step_not_templates_step(): void
    {
        Queue::fake();

        Http::fake([
            '*PHONE123456*' => Http::response([
                'id' => 'PHONE123456',
                'display_phone_number' => '+441234567890',
                'verified_name' => 'Test Business',
                'status' => 'CONNECTED',
            ], 200),
            '*WABA1234567890*' => Http::response([
                'id' => 'WABA1234567890',
                'name' => 'Test WABA',
                'status' => 'APPROVED',
                'account_review_status' => 'APPROVED',
                'business_verification_status' => 'verified',
            ], 200),
        ]);

        $gym = Gym::factory()->create();

        $owner = $this->actingAsGymOwner();
        $owner->gyms()->attach($gym);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->post(route('whatsapp.embedded-signup.manual-connect', ['tenant' => $gym->slug]), [
            'waba_id' => 'WABA1234567890',
            'manual_phone_number' => '+441234567890',
            'waba_phone_number_id' => 'PHONE123456',
            'meta_access_token' => 'EAABTEST',
        ]);

        $location = $response->headers->get('Location') ?? '';

        // Must land on whats-app-business step, NOT templates step.
        $response->assertRedirect();
        $this->assertStringNotContainsString('setup_step=4', $location);
        $this->assertStringContainsString('setup_step=whats-app-business', $location);
        $response->assertSessionHas('success');
        $response->assertSessionHas('templates_import_pending', true);
        Queue::assertPushed(ImportWhatsAppTemplatesJob::class);
    }
}
