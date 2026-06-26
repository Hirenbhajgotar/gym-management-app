<?php

namespace App\Filament\Admin\WhatsAppSetup\Concerns;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
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

    public ?WhatsAppAccountStatusEnum $accountStatus = null;

    public ?WhatsAppAccountVerificationStatusEnum $verificationStatus = null;

    public bool $hasExistingNumber = false;

    public function isWabaConnected(): bool
    {
        return $this->whatsappAccount !== null;
    }

    public function isWabaVerified(): bool
    {
        return $this->isWabaConnected() && $this->verificationStatus === WhatsAppAccountVerificationStatusEnum::VERIFIED;
    }

    public function isWabaPending(): bool
    {
        return $this->isWabaConnected() && $this->verificationStatus === WhatsAppAccountVerificationStatusEnum::PENDING;
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
        $this->welcomeCreditsClaimed = $this->creditBalance?->welcome_credits_claimed ?? false;
        $this->isExistWhatsAppTemplates = WhatsAppTemplate::isExistsForGym((int) $gymId);

        // Load WABA status and Verification status for UI state management
        if ($this->whatsappAccount) {
            $this->accountStatus = $this->whatsappAccount->status instanceof WhatsAppAccountStatusEnum
                ? $this->whatsappAccount->status
                : ($this->whatsappAccount->status ? WhatsAppAccountStatusEnum::tryFrom($this->whatsappAccount->status) : null);

            $this->verificationStatus = $this->whatsappAccount->verification_status instanceof WhatsAppAccountVerificationStatusEnum
                ? $this->whatsappAccount->verification_status
                : ($this->whatsappAccount->verification_status ? WhatsAppAccountVerificationStatusEnum::tryFrom($this->whatsappAccount->verification_status) : null);
        }
    }

    protected function determineViewState(): void
    {
        $requestedStep = request()->query('step');

        // If a step is explicitly requested (e.g., after OAuth callback), use it directly.
        // This handles redirects from EmbeddedSignupController with ?step=N.
        if ($requestedStep) {
            abort_if(! $this->isStepAccessible((string) $requestedStep), 403, 'Unauthorized to access this step.');

            $this->viewState = 'setup';
            // $this->setupStep = (int) $requestedStep;
            $this->setupStep = (int) $this->determineSetupStep();

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

        if ($this->whatsappAccount->isActive() && $this->phoneNumber) {
            $this->viewState = 'active';

            return;
        }

        // Route non-active/non-pending statuses to named viewStates for clear UX messaging
        if ($this->whatsappAccount->isWabaRestricted() || $this->whatsappAccount->isWabaSuspended() || $this->whatsappAccount->isWabaBlocked()) {
            $this->viewState = 'account_issue';

            return;
        }

        $this->viewState = 'error';
    }

    public function determineSetupStep(): int
    {
        $provider = $this->whatsappAccount?->provider_type;

        // Twilio: Account creation step (Step 2)
        if ($provider !== WhatsAppProviderTypeEnum::WABA) {
            if (blank($this->whatsappAccount?->twilio_account_sid)) {
                return SetupWizardSchema::TWILIO_ACCOUNT_STEP; // TwilioAccountStep
            }

            // Twilio: Phone number step (Step 3)
            if (! $this->phoneNumber) {
                return SetupWizardSchema::TWILIO_PHONE_NUMBER_STEP; // TwilioPhoneNumberStep
            }
        }

        // WABA: Account setup step (Step 2)
        if ($provider === WhatsAppProviderTypeEnum::WABA) {
            if (blank($this->whatsappAccount?->waba_id)) {
                return SetupWizardSchema::WABA_ACCOUNT_STEP; // WabaAccountStep
            }

            // Stay on WabaAccountStep until WABA is ACTIVE (not just PENDING)
            if ($this->accountStatus !== WhatsAppAccountStatusEnum::ACTIVE) {
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

    private function isStepAccessible(string $requestedStep): bool
    {
        $step = (int) $this->getStepNumberFromRequestStep($requestedStep);

        if (! $this->whatsappAccount) {
            return $step === 1; // Only provider selection is allowed
        }

        $provider = $this->whatsappAccount->provider_type;

        if ($provider === WhatsAppProviderTypeEnum::TWILIO) {
            // Twilio specific step validation
            if ($step === SetupWizardSchema::TWILIO_ACCOUNT_STEP) {
                return true; // Always allow account step if account not connected
            }

            if ($step === SetupWizardSchema::TWILIO_PHONE_NUMBER_STEP) {
                // Phone number step only allowed if account is connected
                return filled($this->whatsappAccount->twilio_account_sid);
            }

            if ($step === SetupWizardSchema::TWILIO_TEMPLATES_STEP) {
                // Templates step only allowed if phone number is connected
                return $this->phoneNumber !== null;
            }

            if ($step === SetupWizardSchema::TWILIO_WELCOME_CREDITS_STEP) {
                // Welcome credits step only allowed if templates are created
                return $this->phoneNumber !== null && $this->templatesCreated;
            }
        }

        // For WABA users, allow any step after connection
        if ($provider === WhatsAppProviderTypeEnum::WABA) {
            // Allow WABA-related steps if account is connected
            // Steps 2 (WABA account), 3 (Templates), 4 (Welcome Credits)
            return $step >= SetupWizardSchema::WABA_ACCOUNT_STEP && $step <= SetupWizardSchema::WABA_WELCOME_CREDITS_STEP;
        }

        // Default: only allow current or previous steps (linear progression)
        return $step <= $this->setupStep;
    }

    private function getStepNumberFromRequestStep(string $queryStep): ?int
    {
        $provider = $this->whatsappAccount?->provider_type;

        return match ($queryStep) {
            'select-provider' => SetupWizardSchema::PROVIDER_SELECTION_STEP,
            'twilio-account' => SetupWizardSchema::TWILIO_ACCOUNT_STEP,
            'phone-number' => SetupWizardSchema::TWILIO_PHONE_NUMBER_STEP,
            'templates' => $provider === WhatsAppProviderTypeEnum::TWILIO ? SetupWizardSchema::TWILIO_TEMPLATES_STEP : SetupWizardSchema::WABA_TEMPLATES_STEP,
            'welcome-credits' => $provider === WhatsAppProviderTypeEnum::TWILIO ? SetupWizardSchema::TWILIO_WELCOME_CREDITS_STEP : SetupWizardSchema::WABA_WELCOME_CREDITS_STEP,
            'whats-app-business' => SetupWizardSchema::WABA_ACCOUNT_STEP,
            default => null,
        };
    }
}
