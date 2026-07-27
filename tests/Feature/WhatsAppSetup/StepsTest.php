<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsAppSetup;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\WhatsAppSetup\Steps\ProviderSelectionStep;
use App\Filament\Admin\WhatsAppSetup\Steps\TemplatesStep;
use App\Filament\Admin\WhatsAppSetup\Steps\TwilioAccountStep;
use App\Filament\Admin\WhatsAppSetup\Steps\TwilioPhoneNumberStep;
use App\Filament\Admin\WhatsAppSetup\Steps\WabaAccountStep;
use App\Filament\Admin\WhatsAppSetup\Steps\WelcomeCreditsStep;
use Filament\Forms\Components\Wizard\Step;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupPageStub;
use Tests\WhatsAppSetupTestCase;

/**
 * Verifies each wizard Step builds a valid Step component and that the
 * WABA step is only visible for the WABA provider (the swap logic that
 * keeps the 6-step wizard valid for both providers).
 */
class StepsTest extends WhatsAppSetupTestCase
{
    private function pageStub(string $provider): WhatsAppSetupPageStub
    {
        $page = new WhatsAppSetupPageStub();
        $page->selectedProvider = $provider;
        $page->data = ['provider_type' => $provider];

        return $page;
    }

    #[Test]
    public function every_step_returns_a_step_instance(): void
    {
        $page = $this->pageStub(WhatsAppProviderTypeEnum::WABA->value);

        $steps = [
            ProviderSelectionStep::make($page),
            TwilioAccountStep::make($page),
            WabaAccountStep::make($page),
            TwilioPhoneNumberStep::make($page),
            TemplatesStep::make($page),
            WelcomeCreditsStep::make($page),
        ];

        foreach ($steps as $step) {
            $this->assertInstanceOf(Step::class, $step);
        }
    }

    #[Test]
    public function waba_account_step_is_visible_for_waba_provider(): void
    {
        $page = $this->pageStub(WhatsAppProviderTypeEnum::WABA->value);

        $step = WabaAccountStep::make($page);

        $this->assertTrue($step->isVisible());
    }

    #[Test]
    public function waba_account_step_is_hidden_for_twilio_provider(): void
    {
        $page = $this->pageStub(WhatsAppProviderTypeEnum::TWILIO->value);

        $step = WabaAccountStep::make($page);

        $this->assertFalse($step->isVisible());
    }

    #[Test]
    public function twilio_account_step_is_visible_for_twilio_provider(): void
    {
        $page = $this->pageStub(WhatsAppProviderTypeEnum::TWILIO->value);

        $step = TwilioAccountStep::make($page);

        $this->assertTrue($step->isVisible());
    }
}
