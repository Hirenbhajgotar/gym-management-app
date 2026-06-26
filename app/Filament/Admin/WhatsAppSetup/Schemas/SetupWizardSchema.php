<?php

namespace App\Filament\Admin\WhatsAppSetup\Schemas;

use App\Filament\Admin\WhatsAppSetup\Steps\ProviderSelectionStep;
use App\Filament\Admin\WhatsAppSetup\Steps\TwilioAccountStep;
use App\Filament\Admin\WhatsAppSetup\Steps\WabaAccountStep;
use App\Filament\Admin\WhatsAppSetup\Steps\TwilioPhoneNumberStep;
use App\Filament\Admin\WhatsAppSetup\Steps\TemplatesStep;
use App\Filament\Admin\WhatsAppSetup\Steps\WelcomeCreditsStep;
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
                ProviderSelectionStep::make($page),
                TwilioAccountStep::make($page), // Contains logic to swap to WABA internally
                WabaAccountStep::make($page),   // Contains logic to swap to Twilio internally
                TwilioPhoneNumberStep::make($page),
                TemplatesStep::make($page),
                WelcomeCreditsStep::make($page),
            ])
                ->persistStepInQueryString()
                ->startOnStep($page->setupStep)
                ->nextAction(fn ($action) => $action->label('Continue')->icon('heroicon-o-arrow-right'))
                ->previousAction(fn ($action) => $action->label('Back')->icon('heroicon-o-arrow-left')),
        ];
    }
}
