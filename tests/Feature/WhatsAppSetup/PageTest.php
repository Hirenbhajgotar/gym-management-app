<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsAppSetup;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Models\Gym;
use App\Models\User;
use App\Models\WabaSetupSession;
use App\Models\WhatsAppAccount;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupTestCase;
use Throwable;

/**
 * Feature tests for the WhatsAppSetup page view-state / step determination.
 *
 * These cover the bug where a post-embedded-signup redirect to ?step=2
 * produced a 403 and subsequent refreshes produced ?step=undefined / a
 * blank wizard. The fix separates the OAuth landing signal (?setup_step)
 * from the Filament Wizard's own `step` query param.
 */
class PageTest extends WhatsAppSetupTestCase
{
    protected User $user;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsGymOwner();
        $tenant = Filament::getTenant();
        assert($tenant instanceof Gym);
        $this->gym = $tenant;
    }

    protected function tearDown(): void
    {
        try {
            Filament::setTenant(null);
        } catch (Throwable $_) {
            // ignore
        }

        parent::tearDown();
    }

    #[Test]
    public function it_shows_onboarding_state_when_no_whatsapp_account_exists(): void
    {
        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'onboarding');
        $setupPage->assertSet('setupStep', 1);
    }

    #[Test]
    public function it_shows_setup_state_when_account_is_pending(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
    }

    #[Test]
    public function it_sets_step_3_for_waba_account_missing_waba_id(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 3);
    }

    #[Test]
    public function it_sets_step_3_for_pending_waba_account_with_waba_id(): void
    {
        // This is the exact post-embedded-signup state: account exists,
        // waba_id filled, status PENDING (not yet ACTIVE).
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 3);
    }

    #[Test]
    public function it_lands_on_setup_when_setup_step_whats_app_business_requested_with_waba_account(): void
    {
        // Simulates the EmbeddedSignupController redirect to ?setup_step=whats-app-business.
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        request()->merge(['setup_step' => 'whats-app-business']);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 3);
    }

    #[Test]
    public function it_lands_on_setup_when_setup_step_3_requested_for_twilio_with_account_sid(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        request()->merge(['setup_step' => '3']);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 3);
    }

    #[Test]
    public function it_gracefully_lands_on_onboarding_when_setup_step_requested_without_an_account(): void
    {
        // The previous behavior aborted with 403 here. A hard 403 on a normal
        // post-signup navigation broke the flow and produced a blank wizard.
        request()->merge(['setup_step' => 'whats-app-business']);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'onboarding');
        $setupPage->assertSet('setupStep', 1);
    }

    #[Test]
    public function it_ignores_the_wizard_step_query_param_to_avoid_collisions(): void
    {
        // The Filament Wizard owns the `step` query param via
        // persistStepInQueryString(). The page must NOT treat a bare `step`
        // param (including the malformed `step=undefined` from a previous
        // broken redirect) as a landing signal, otherwise Livewire serializes
        // `step=undefined` and renders a blank wizard with only the Continue
        // button.
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        request()->merge(['step' => 'undefined']);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 3);
    }

    #[Test]
    public function it_does_not_403_when_step_query_param_is_malformed(): void
    {
        // Regression guard: a malformed `step` param must never abort.
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        request()->merge(['step' => 'undefined']);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
    }

    #[Test]
    public function it_rejects_expired_oauth_state(): void
    {
        $session = WabaSetupSession::createForGym($this->gym->id);
        $session->update(['expires_at' => now()->subHour()]);

        $this->session([
            'embedded_signup_tenant_id' => $this->gym->id,
            'embedded_signup_tenant_slug' => $this->gym->slug,
        ]);

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'test_code',
            'state' => $session->session_id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Invalid OAuth state');
    }

    #[Test]
    public function it_rejects_tenant_mismatch_in_oauth_state(): void
    {
        $otherGym = Gym::factory()->create();
        $session = WabaSetupSession::createForGym($otherGym->id);

        $this->session([
            'embedded_signup_tenant_id' => $this->gym->id,
            'embedded_signup_tenant_slug' => $this->gym->slug,
        ]);

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'test_code',
            'state' => $session->session_id,
        ]));

        $response->assertSessionHas('error', 'Invalid OAuth state');
    }
}
