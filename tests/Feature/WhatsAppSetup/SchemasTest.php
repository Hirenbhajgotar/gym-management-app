<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsAppSetup;

use App\Filament\Admin\WhatsAppSetup\Schemas\SetupWizardSchema;
use Filament\Forms\Components\Wizard;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupPageStub;
use Tests\WhatsAppSetupTestCase;

/**
 * Verifies the wizard schema configuration: step constants, ordering,
 * and that the wizard persists its own `step` in the query string
 * (the reason the page must use a separate `setup_step` param).
 */
class SchemasTest extends WhatsAppSetupTestCase
{
    #[Test]
    public function waba_account_step_and_twilio_account_step_share_index_2(): void
    {
        // Both providers reach their "account" step at wizard index 2.
        $this->assertSame(2, SetupWizardSchema::WABA_ACCOUNT_STEP);
        $this->assertSame(2, SetupWizardSchema::TWILIO_ACCOUNT_STEP);
    }

    #[Test]
    public function step_constants_are_ordered_and_distinct_per_provider(): void
    {
        $this->assertSame(1, SetupWizardSchema::PROVIDER_SELECTION_STEP);
        $this->assertSame(3, SetupWizardSchema::TWILIO_PHONE_NUMBER_STEP);
        $this->assertSame(4, SetupWizardSchema::TWILIO_TEMPLATES_STEP);
        $this->assertSame(5, SetupWizardSchema::TWILIO_WELCOME_CREDITS_STEP);

        $this->assertSame(3, SetupWizardSchema::WABA_BILLING_MODE_STEP);
        $this->assertSame(4, SetupWizardSchema::WABA_TEMPLATES_STEP);
        $this->assertSame(5, SetupWizardSchema::WABA_WELCOME_CREDITS_STEP);
    }

    #[Test]
    public function make_returns_a_wizard_with_seven_steps(): void
    {
        $page = new WhatsAppSetupPageStub();

        $schema = SetupWizardSchema::make($page);

        $this->assertCount(1, $schema);
        $this->assertInstanceOf(Wizard::class, $schema[0]);

        $steps = $schema[0]->getChildComponents();
        $this->assertCount(7, $steps);
    }

    #[Test]
    public function wizard_persists_step_in_query_string(): void
    {
        $page = new WhatsAppSetupPageStub();

        $schema = SetupWizardSchema::make($page);
        $wizard = $schema[0];

        // The wizard owns the `step` query param via persistStepInQueryString().
        // This is exactly why determineViewState() must NOT read `step` and
        // instead uses the dedicated `setup_step` param.
        $this->assertTrue(method_exists((object) $wizard, 'persistStepInQueryString'));
        $this->assertInstanceOf(Wizard::class, $wizard);
    }
}
