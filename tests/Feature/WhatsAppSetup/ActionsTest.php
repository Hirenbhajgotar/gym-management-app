<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsAppSetup;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Models\Gym;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupTestCase;
use Throwable;

/**
 * Feature tests for the WhatsAppSetup action classes.
 *
 * Verifies that StartSetupAction and StartEmbeddedSignupAction redirect
 * using the dedicated `setup_step` query param (not `step`, which is owned
 * by the Filament Wizard via persistStepInQueryString()).
 */
class ActionsTest extends WhatsAppSetupTestCase
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
    public function start_setup_action_redirects_to_setup_step_when_account_exists(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('startSetupAction');
        $setupPage->assertRedirectToRoute('filament.admin.pages.whats-app-setup', [
            'tenant' => $this->gym->slug,
            'setup_step' => 2,
        ]);
    }

    #[Test]
    public function start_setup_action_sets_view_state_inline_when_no_account(): void
    {
        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('startSetupAction');
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 1);
        $setupPage->assertNoRedirect();
    }

    #[Test]
    public function start_embedded_signup_action_redirects_to_embedded_signup_route(): void
    {
        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('startEmbeddedSignupAction');
        $setupPage->assertRedirectToRoute('whatsapp.embedded-signup.redirect', [
            'tenant' => $this->gym->slug,
        ]);
    }
}
