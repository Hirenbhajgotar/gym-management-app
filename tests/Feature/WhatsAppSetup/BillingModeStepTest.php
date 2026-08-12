<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsAppSetup;

use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\WhatsAppSetup\Steps\BillingModeStep;
use App\Models\Gym;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Filament\Facades\Filament;
use Filament\Forms\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupPageStub;
use Tests\WhatsAppSetupTestCase;
use Throwable;

/**
 * BillingModeStep tests: WABA-only visibility, billing-mode persistence
 * through the after-validation hook, and the Halt guard when no WhatsApp
 * account exists for the gym yet.
 */
class BillingModeStepTest extends WhatsAppSetupTestCase
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

    /**
     * @param  array{provider_type?: string, billing_mode?: string}  $data
     */
    private function makeStep(string $provider = WhatsAppProviderTypeEnum::WABA->value, array $data = []): Step
    {
        $page = new WhatsAppSetupPageStub();
        $page->data = array_merge(['provider_type' => $provider], $data);

        return BillingModeStep::make($page);
    }

    #[Test]
    public function it_returns_a_step_instance(): void
    {
        $this->assertInstanceOf(Step::class, $this->makeStep());
    }

    #[Test]
    public function it_is_visible_for_waba_provider(): void
    {
        $this->assertTrue($this->makeStep(WhatsAppProviderTypeEnum::WABA->value)->isVisible());
    }

    #[Test]
    public function it_is_hidden_for_twilio_provider(): void
    {
        $this->assertFalse($this->makeStep(WhatsAppProviderTypeEnum::TWILIO->value)->isVisible());
    }

    #[Test]
    public function it_persists_selected_billing_mode_after_validation(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'billing_mode' => WhatsAppBillingModeEnum::PAY_VIA_APP,
        ]);

        $step = $this->makeStep(data: ['billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED->value]);

        $step->callAfterValidation();

        $this->assertDatabaseHas('whatsapp_accounts', [
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED->value,
        ]);
    }

    #[Test]
    public function it_halts_when_no_whatsapp_account_exists(): void
    {
        $step = $this->makeStep(data: ['billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED->value]);

        $this->expectException(Halt::class);

        $step->callAfterValidation();
    }

    #[Test]
    public function it_skips_update_when_no_billing_mode_is_selected(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::PAY_VIA_APP,
        ]);

        $step = $this->makeStep(data: []);

        $step->callAfterValidation();

        $this->assertDatabaseHas('whatsapp_accounts', [
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::PAY_VIA_APP->value,
        ]);
    }
}
