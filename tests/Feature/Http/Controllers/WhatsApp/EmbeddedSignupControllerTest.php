<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\WhatsApp;

use App\Enums\WabaSetupSessionStatusEnum;
use App\Models\Gym;
use App\Models\WabaSetupSession;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupTestCase;

/**
 * Feature tests for App\Http\Controllers\WhatsApp\EmbeddedSignupController.
 *
 * Covers the two reviewed defects:
 *  - M2: the OAuth callback must reject a state that does not belong to the
 *        tenant stored in the session (cross-gym / forged-state protection).
 *  - M3: manualConnect() must redirect to the WABA account step (2), NOT the
 *        templates step (3), because template import runs asynchronously.
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
        Session::put('embedded_signup_tenant_id', $ownerGym->id);
        Session::put('embedded_signup_tenant_slug', $ownerGym->slug);

        // But the OAuth state belongs to a different gym entirely.
        $foreignSession = WabaSetupSession::createForGym($otherGym->id);

        $this->actingAsGymOwner();

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'valid-code',
            'state' => $foreignSession->session_id,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'setup_step=2',
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

        // Fake the Meta Graph API (token exchange + debug_token) with a payload
        // that yields a WABA id so the callback proceeds past the state gate.
        Http::fake([
            'graph.facebook.com/**' => Http::response([
                'access_token' => 'EAABTESTTOKEN',
                'token_type' => 'bearer',
                'expires_in' => 3600,
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
        ]);

        $gym = Gym::factory()->create();

        Session::put('embedded_signup_tenant_id', $gym->id);
        Session::put('embedded_signup_tenant_slug', $gym->slug);

        $validSession = WabaSetupSession::createForGym($gym->id);

        $this->actingAsGymOwner();

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'valid-code',
            'state' => $validSession->session_id,
        ]));

        // No real Meta API is available, so the callback proceeds to token
        // exchange and ultimately fails at the API boundary — but crucially it
        // is NOT rejected at the state/tenant binding gate (no "Invalid OAuth
        // state" error).
        $response->assertRedirect();
        $this->assertNotEquals('Invalid OAuth state', session('error'));
    }

    #[Test]
    public function callback_rejects_an_already_consumed_state(): void
    {
        Queue::fake();

        $gym = Gym::factory()->create();

        Session::put('embedded_signup_tenant_id', $gym->id);
        Session::put('embedded_signup_tenant_slug', $gym->slug);

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
        $this->assertStringContainsString('setup_step=2', $response->headers->get('Location') ?? '');
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

        Session::put('embedded_signup_tenant_id', $gym->id);
        Session::put('embedded_signup_tenant_slug', $gym->slug);

        $expiredSession = WabaSetupSession::createForGym($gym->id);
        $expiredSession->update(['expires_at' => now()->subHour()]);

        $this->actingAsGymOwner();

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'valid-code',
            'state' => $expiredSession->session_id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Invalid OAuth state');
    }

    #[Test]
    public function manual_connect_redirects_to_account_step_not_templates_step(): void
    {
        Queue::fake();

        $gym = Gym::factory()->create();

        $owner = $this->actingAsGymOwner();
        $owner->gyms()->attach($gym);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // NOTE: The success path of manualConnect() also dispatches the
        // ImportWhatsAppTemplatesJob and flashes `templates_import_pending`.
        // Reaching that path currently requires MetaWabaService::connectManualWaba
        // to persist a WhatsAppPhoneNumber, which hits a pre-existing,
        // out-of-scope bug (extractCountryCode() over-captures a 3-digit code
        // that overflows the 2-char country_code column). This test therefore
        // asserts the essential M3 regression guard: manualConnect() must NEVER
        // redirect to the templates step (3). Both the success and error
        // branches now target the WABA account step (2), so the wizard can
        // advance naturally once the async import completes.
        $response = $this->post(route('whatsapp.embedded-signup.manual-connect', ['tenant' => $gym->slug]), [
            'waba_id' => 'WABA1234567890',
            'manual_phone_number' => '+441234567890',
            'waba_phone_number_id' => 'PHONE123456',
            'meta_access_token' => 'EAABTEST',
        ]);

        $location = $response->headers->get('Location') ?? '';

        // M3: must NOT land on the templates step (3); the import is async so
        // the user must remain on the account step (2).
        $response->assertRedirect();
        $this->assertStringNotContainsString('setup_step=3', $location);
        $this->assertStringContainsString('setup_step=2', $location);
    }
}
