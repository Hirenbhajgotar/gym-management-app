<?php

namespace App\Filament\Admin\WhatsAppSetup\Schemas;

use App\Filament\Admin\WhatsAppSetup\Steps;
use Filament\Forms\Components\Wizard;

class SetupWizardSchema
{
    /**
     * Select Provider
     */
    public const PROVIDER_SELECTION_STEP = 1;

    /**
     * Twilio Account
     */
    public const TWILIO_ACCOUNT_STEP = 2;

    /**
     * WhatsApp Business
     */
    public const WABA_ACCOUNT_STEP = 2;

    /**
     * Phone Number
     */
    public const TWILIO_PHONE_NUMBER_STEP = 3;

    /**
     * Templates
     */
    public const TWILIO_TEMPLATES_STEP = 4;

    /**
     * Templates
     */
    public const WABA_TEMPLATES_STEP = 3;

    /**
     * Welcome Credits
     */
    public const TWILIO_WELCOME_CREDITS_STEP = 5;

    /**
     * Welcome Credits
     */
    public const WABA_WELCOME_CREDITS_STEP = 4;

    public static function make($page): array
    {
        return [
            Wizard::make([
                Steps\ProviderSelectionStep::make($page),
                Steps\TwilioAccountStep::make($page), // Contains logic to swap to WABA internally
                Steps\WabaAccountStep::make($page),   // Contains logic to swap to Twilio internally
                Steps\TwilioPhoneNumberStep::make($page),
                Steps\TemplatesStep::make($page),
                Steps\WelcomeCreditsStep::make($page),
            ])
                ->persistStepInQueryString()
                ->startOnStep($page->setupStep)
                ->nextAction(fn ($action) => $action->label('Continue')->icon('heroicon-o-arrow-right'))
                ->previousAction(fn ($action) => $action->label('Back')->icon('heroicon-o-arrow-left')),
        ];
    }
}
