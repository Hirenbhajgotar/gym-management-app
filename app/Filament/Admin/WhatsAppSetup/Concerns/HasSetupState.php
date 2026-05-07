<?php

namespace App\Filament\Admin\WhatsAppSetup\Concerns;

use App\Enums\WabaStatusEnum;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\WhatsAppSetup\Schemas\SetupWizardSchema;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;

trait HasSetupState
{
    /** @var array{provider_type?: string, business_name?: string, ...} */
    public ?array $data = [];

    public ?WhatsAppAccount $whatsappAccount = null;

    public ?WhatsAppPhoneNumber $phoneNumber = null;

    public ?WhatsAppMessageCredit $creditBalance = null;

    public string $viewState = 'loading';

    public int $setupStep = 1;

    public int $totalSteps = 6;

    /** @var array<int, array{phoneNumber: string, friendlyName: string, locality: string, region: string, isoCountry: string}> */
    public array $availableNumbers = [];

    public bool $availableNumbersLoaded = false;

    public ?string $selectedNumber = null;

    public bool $isSearching = false;

    /** @var array{business_name: string, business_email: string, business_description: string} */
    public array $businessProfile = [];

    public bool $welcomeCreditsClaimed = false;

    // public bool $useIndividualWaba = false;

    public bool $requiresEmbeddedSignup = false;

    public ?string $embeddedSignupUrl = null;

    public bool $accountCreated = false;

    public bool $profileSaved = false;

    public bool $wabaCreated = false;

    public bool $numberPurchased = false;

    public string $phoneNumberSource = 'use_own_number';

    public string $senderSid = '';

    public bool $ownNumberRegistered = false;

    public bool $templatesCreated = false;

    public string $wabaConnectionMethod = 'create_new';

    public bool $isExistWhatsAppTemplates = false;

    public string $selectedProvider = WhatsAppProviderTypeEnum::WABA->value;

    public bool $hasExistingNumber = false;

    public function isWabaConnected(): bool
    {
        return $this->whatsappAccount !== null && $this->whatsappAccount->waba_id !== null;
    }

    public function isPhoneNumberConnected(): bool
    {
        return $this->phoneNumber !== null;
    }

    protected function loadWhatsAppData(): void
    {
        $gymId = $this->getGymId();
        $this->whatsappAccount = WhatsAppAccount::with('phoneNumber')->where('gym_id', $gymId)->first();
        $this->phoneNumber = $this->whatsappAccount?->phoneNumber;
        $this->creditBalance = WhatsAppMessageCredit::where('gym_id', $gymId)->first();
        $this->welcomeCreditsClaimed = $this->creditBalance->welcome_credits_claimed ?? false;
        $this->isExistWhatsAppTemplates = (bool) WhatsAppTemplate::isExistsForGym((int) $gymId);
    }

    protected function determineViewState(): void
    {
        $requestedStep = request()->query('step');

        // If a step is explicitly requested (e.g., after OAuth callback), use it directly.
        // This handles redirects from EmbeddedSignupController with ?step=N.
        if ($requestedStep) {
            $this->viewState = 'setup';
            $this->setupStep = (int) $requestedStep;

            return;
        }

        if (! $this->whatsappAccount) {
            $this->viewState = 'onboarding';
            $this->setupStep = 1;

            return;
        }

        $this->accountCreated = true;

        if ($this->whatsappAccount->status === WhatsAppAccountStatusEnum::PENDING) {
            $this->viewState = 'setup';
            $this->setupStep = $this->determineSetupStep();

            return;
        }

        if ($this->whatsappAccount->status === WhatsAppAccountStatusEnum::ACTIVE && $this->phoneNumber) {
            $this->viewState = 'active';

            return;
        }

        $this->viewState = 'error';
    }

    public function determineSetupStep(): int
    {
        $provider = $this->whatsappAccount?->provider_type;

        // Twilio: Account creation step (Step 2)
        if ($provider !== WhatsAppProviderTypeEnum::WABA) {
            if (empty($this->whatsappAccount?->twilio_account_sid)) {
                return SetupWizardSchema::TWILIO_ACCOUNT_STEP; // TwilioAccountStep
            }

            // Twilio: Phone number step (Step 3)
            if (! $this->phoneNumber) {
                return SetupWizardSchema::TWILIO_PHONE_NUMBER_STEP; // TwilioPhoneNumberStep
            }
        }

        // WABA: Account setup step (Step 2)
        if ($provider === WhatsAppProviderTypeEnum::WABA) {
            if (empty($this->whatsappAccount?->waba_id)) {
                return SetupWizardSchema::WABA_ACCOUNT_STEP; // WabaAccountStep
            }

            // Stay on WabaAccountStep until WABA is ACTIVE (not just PENDING)
            if (empty($this->whatsappAccount?->waba_status) || $this->whatsappAccount?->waba_status !== WabaStatusEnum::ACTIVE) {
                return SetupWizardSchema::WABA_ACCOUNT_STEP; // WabaAccountStep
            }
        }

        // Both providers: phone number is confirmed at this point
        if ($provider !== WhatsAppProviderTypeEnum::WABA) {
            $this->numberPurchased = true;
        }

        // Both: Templates step
        $defaultTemplates = WhatsAppTemplate::where('gym_id', $this->getGymId())->count();
        if ($defaultTemplates === 0) {
            return $provider === WhatsAppProviderTypeEnum::WABA ? SetupWizardSchema::WABA_TEMPLATES_STEP : SetupWizardSchema::TWILIO_TEMPLATES_STEP; // TemplatesStep
        }

        $this->templatesCreated = true;

        // Both: Welcome credits step
        if (! $this->welcomeCreditsClaimed) {
            return $provider === WhatsAppProviderTypeEnum::WABA ? SetupWizardSchema::WABA_WELCOME_CREDITS_STEP : SetupWizardSchema::TWILIO_WELCOME_CREDITS_STEP; // WelcomeCreditsStep
        }

        // All steps complete — return last step
        // WABA: WhatsApp Business Account step
        // Twilio: Account step
        return $provider === WhatsAppProviderTypeEnum::WABA ? SetupWizardSchema::WABA_ACCOUNT_STEP : SetupWizardSchema::TWILIO_ACCOUNT_STEP;
    }
}
