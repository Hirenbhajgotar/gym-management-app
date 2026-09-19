<?php

declare(strict_types=1);

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
    /**
     * @var array{
     *     provider_type?: string,
     *     business_name?: string,
     *     business_email?: string,
     *     business_description?: string,
     *     waba_connection_method?: string,
     *     waba_id?: string,
     *     meta_access_token?: string,
     *     waba_phone_number_id?: string,
     *     manual_phone_number?: string,
     *     billing_mode?: string,
     *     ...
     * }
     */
    public ?array $data = [];

    public ?WhatsAppAccount $whatsappAccount = null;

    public ?WhatsAppPhoneNumber $phoneNumber = null;

    public ?WhatsAppMessageCredit $creditBalance = null;

    public string $viewState = 'loading';

    public int $setupStep = 1;

    public int $totalSteps = 7;

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
        $this->creditBalance = WhatsAppMessageCredit::query()->where('gym_id', $gymId)->first();
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
        // A dedicated `setup_step` query param signals "land on the setup wizard"
        // after an OAuth/embedded-signup redirect or a forced full-page reload.
        // We deliberately do NOT reuse the Wizard's own `step` query param
        // (persisted via persistStepInQueryString) — sharing it caused Livewire
        // to serialize `step=undefined` into the URL and render a blank wizard.
        $requestedStep = request()->query('setup_step');

        // If a step is explicitly requested (e.g., after OAuth callback), enter the
        // setup wizard. The actual step shown is always recomputed by
        // determineSetupStep() so we never land on a stale/invalid position.
        // We only honor this when an account already exists (setup is in progress);
        // otherwise an unauthenticated/invalid request simply lands on onboarding.
        if (($requestedStep !== null && $requestedStep !== '') && $this->whatsappAccount) {
            $resolved = $this->getStepNumberFromRequestStep((string) $requestedStep);

            if ($resolved !== null && $this->isStepAccessible((string) $resolved)) {
                $this->setupStep = $resolved;
            } else {
                $this->setupStep = $this->determineSetupStep();
            }
            $this->viewState = 'setup';

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

        // WABA: Account setup step (Step 3)
        // WABA: Billing mode step (Step 2) — only for WABA
        if ($provider === WhatsAppProviderTypeEnum::WABA) {
            if (blank($this->whatsappAccount?->waba_id)) {
                return SetupWizardSchema::WABA_ACCOUNT_STEP; // WabaAccountStep
            }

            // Stay on WabaAccountStep until WABA is ACTIVE (not just PENDING)
            if ($this->accountStatus !== WhatsAppAccountStatusEnum::ACTIVE) {
                return SetupWizardSchema::WABA_ACCOUNT_STEP; // WabaAccountStep
            }

            if ($this->verificationStatus === WhatsAppAccountVerificationStatusEnum::PENDING) {
                return SetupWizardSchema::WABA_ACCOUNT_STEP; // WabaAccountStep
            }

            if (! $this->whatsappAccount?->hasBillingMode()) {
                return SetupWizardSchema::WABA_BILLING_MODE_STEP; // BillingModeStep
            }
        }

        // Both providers: phone number is confirmed at this point
        if ($provider !== WhatsAppProviderTypeEnum::WABA) {
            $this->numberPurchased = true;
        }

        // Both: Templates step
        $defaultTemplates = WhatsAppTemplate::query()->where('gym_id', $this->getGymId())->count();
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
        $step = $this->getStepNumberFromRequestStep($requestedStep);

        if ($step === null) {
            return false; // Invalid step requested
        }

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
                $hasTemplates = WhatsAppTemplate::query()->where('gym_id', $this->getGymId())->exists();

                return $this->phoneNumber !== null && $hasTemplates;
            }
        }

        // For WABA users, allow any step after connection
        if ($provider === WhatsAppProviderTypeEnum::WABA) {
            // Step 3: WABA Account
            if ($step === SetupWizardSchema::WABA_ACCOUNT_STEP) {
                return true;
            }
            // Step 2: Billing Mode
            if ($step === SetupWizardSchema::WABA_BILLING_MODE_STEP) {
                return filled($this->whatsappAccount->waba_id)
                    && $this->accountStatus === WhatsAppAccountStatusEnum::ACTIVE;
            }
            // Step 4: Templates
            if ($step === SetupWizardSchema::WABA_TEMPLATES_STEP) {
                return filled($this->whatsappAccount->waba_id)
                    && $this->accountStatus === WhatsAppAccountStatusEnum::ACTIVE;
            }
            // Step 5: Welcome Credits
            if ($step === SetupWizardSchema::WABA_WELCOME_CREDITS_STEP) {
                return $this->phoneNumber !== null && $this->templatesCreated;
            }
        }

        // Default: only allow current or previous steps (linear progression)
        return $step <= $this->setupStep;
    }

    private function getStepNumberFromRequestStep(string $queryStep): ?int
    {
        if (is_numeric($queryStep)) {
            return (int) $queryStep;
        }

        $provider = $this->whatsappAccount?->provider_type;

        return match ($queryStep) {
            'select-provider' => SetupWizardSchema::PROVIDER_SELECTION_STEP,
            'twilio-account' => SetupWizardSchema::TWILIO_ACCOUNT_STEP,
            'phone-number' => SetupWizardSchema::TWILIO_PHONE_NUMBER_STEP,
            'templates' => $provider === WhatsAppProviderTypeEnum::TWILIO ? SetupWizardSchema::TWILIO_TEMPLATES_STEP : SetupWizardSchema::WABA_TEMPLATES_STEP,
            'welcome-credits' => $provider === WhatsAppProviderTypeEnum::TWILIO ? SetupWizardSchema::TWILIO_WELCOME_CREDITS_STEP : SetupWizardSchema::WABA_WELCOME_CREDITS_STEP,
            'billing-mode' => SetupWizardSchema::WABA_BILLING_MODE_STEP,
            'whats-app-business' => SetupWizardSchema::WABA_ACCOUNT_STEP,
            default => null,
        };
    }
}
