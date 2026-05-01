<?php

namespace App\Filament\Admin\WhatsAppSetup\Concerns;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;

/**
 * Shared state properties and lifecycle methods for the WhatsApp setup wizard.
 * Used by WhatsAppSetup page and step classes.
 */
trait HasSetupState
{
    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?WhatsAppAccount $whatsappAccount = null;

    public ?WhatsAppPhoneNumber $phoneNumber = null;

    public ?WhatsAppMessageCredit $creditBalance = null;

    public string $viewState = 'loading';

    public int $setupStep = 1;

    public int $totalSteps = 5;

    /** @var array<int, array{phone_number: string, capabilities: array<string>, rate: float}> */
    public array $availableNumbers = [];

    public bool $availableNumbersLoaded = false;

    public ?string $selectedNumber = null;

    public bool $isSearching = false;

    /** @var array<string, string> */
    public array $businessProfile = [];

    public bool $welcomeCreditsClaimed = false;

    public bool $useIndividualWaba = false;

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

    /** @var 'twilio'|'waba' */
    public string $selectedProvider = 'waba';

    public bool $hasExistingNumber = false;

    public function isWabaConnected(): bool
    {
        return $this->whatsappAccount !== null
            && $this->whatsappAccount->waba_id !== null;
    }

    public function isPhoneNumberConnected(): bool
    {
        return $this->phoneNumber !== null;
    }

    protected function loadWhatsAppData(): void
    {
        $gymId = $this->getGymId();

        $this->whatsappAccount = WhatsAppAccount::where('gym_id', $gymId)->first();
        $this->phoneNumber = $this->whatsappAccount?->phoneNumber()->first();
        $this->creditBalance = WhatsAppMessageCredit::where('gym_id', $gymId)->first();
        $this->welcomeCreditsClaimed = $this->creditBalance->welcome_credits_claimed ?? false;
        $this->isExistWhatsAppTemplates = (bool) WhatsAppTemplate::isExistsForGym((int) $gymId);
    }

    protected function determineViewState(): void
    {
        $requestedStep = request()->query('step');

        if ($requestedStep && ! $this->whatsappAccount) {
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

    protected function determineSetupStep(): int
    {
        if (
            ! $this->whatsappAccount?->twilio_account_sid
            && $this->whatsappAccount?->provider_type !== WhatsAppProviderTypeEnum::WABA
        ) {
            return 1;
        }

        if (! $this->whatsappAccount?->waba_id) {
            return 2;
        }

        if (
            ! $this->phoneNumber
            && $this->whatsappAccount?->provider_type !== WhatsAppProviderTypeEnum::WABA
        ) {
            return 2;
        }

        $this->numberPurchased = true;

        $defaultTemplates = WhatsAppTemplate::where('gym_id', $this->getGymId())
            ->where('is_default', true)
            ->count();

        if ($defaultTemplates === 0) {
            return 3;
        }

        $this->templatesCreated = true;

        if (! $this->welcomeCreditsClaimed) {
            return 4;
        }

        return 5;
    }
}
