<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsAppSetup;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\WhatsAppSetup\Schemas\SetupSchema;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupPageStub;
use Tests\WhatsAppSetupTestCase;

/**
 * Pure unit tests (no database) for the WhatsAppSetup schema dispatch and
 * provider enum semantics that back the wizard step logic.
 */
class StateLogicTest extends WhatsAppSetupTestCase
{
    #[Test]
    public function setup_schema_dispatches_onboarding_for_onboarding_view_state(): void
    {
        $page = new WhatsAppSetupPageStub();
        $page->viewState = 'onboarding';

        $schema = SetupSchema::make($page);

        $this->assertNotEmpty($schema);
    }

    #[Test]
    public function setup_schema_dispatches_setup_wizard_for_setup_view_state(): void
    {
        $page = new WhatsAppSetupPageStub();
        $page->viewState = 'setup';

        $schema = SetupSchema::make($page);

        $this->assertNotEmpty($schema);
    }

    #[Test]
    public function setup_schema_returns_empty_array_for_unknown_view_state(): void
    {
        $page = new WhatsAppSetupPageStub();
        $page->viewState = 'something_unknown';

        $schema = SetupSchema::make($page);

        $this->assertSame([], $schema);
    }

    #[Test]
    public function waba_and_twilio_provider_values_are_distinct(): void
    {
        $this->assertNotSame(WhatsAppProviderTypeEnum::WABA->value, WhatsAppProviderTypeEnum::TWILIO->value);
        $this->assertSame('waba', WhatsAppProviderTypeEnum::WABA->value);
        $this->assertSame('twilio', WhatsAppProviderTypeEnum::TWILIO->value);
    }
}
