<?php

namespace App\Filament\Admin\WhatsAppSetup\Concerns;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Services\EmbeddedSignupService;
use App\Services\TwilioAccountManager;
use App\Services\WhatsAppBillingService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Action methods for the WhatsApp setup wizard.
 * All public methods that are invoked by wizard steps (form actions, wizard validation callbacks).
 */
trait HasSetupActions
{
    /**
     * Start the setup wizard from the onboarding screen.
     */
    public function startSetup(): void
    {
        $this->viewState = 'setup';
        $this->setupStep = 1;
    }

    /**
     * Retry the setup process after an error state.
     */
    public function retrySetup(): void
    {
        $this->loadWhatsAppData();
        $this->determineViewState();
    }

    /**
     * Legacy method - redirects to new provider-based method.
     */
    public function createSubaccount(): void
    {
        $this->processTwilioAccountSetup();
    }

    /**
     * Process account creation and provider selection in combined step.
     * Handles both Twilio and WABA flows.
     */
    public function processAccountAndProviderSetup(): void
    {
        $gymId = $this->getGymId();
        $gym = $this->getGym();
        $user = Auth::user();
        $data = $this->data;

        $providerType = $data['provider_type'] ?? 'twilio';
        $phoneNumberSource = $data['phone_number_source'] ?? 'purchase_twilio';

        $providerEnum = $providerType === 'waba'
            ? WhatsAppProviderTypeEnum::WABA
            : WhatsAppProviderTypeEnum::TWILIO;

        $accountData = [
            'gym_id' => $gymId,
            'business_name' => $data['business_name'] ?? $gym?->name ?? $user?->name . ' Gym',
            'business_email' => $data['business_email'] ?? $user?->email,
            'business_description' => $data['business_description'] ?? '',
            'provider_type' => $providerEnum->value,
            'status' => WhatsAppAccountStatusEnum::PENDING,
        ];

        $existingAccount = WhatsAppAccount::where('gym_id', $gymId)->first();

        if ($existingAccount) {
            $existingAccount->update($accountData);
            $this->whatsappAccount = $existingAccount->fresh();
        } else {
            $this->whatsappAccount = WhatsAppAccount::create($accountData);
        }

        if ($providerEnum === WhatsAppProviderTypeEnum::TWILIO) {
            $this->processTwilioSetup($phoneNumberSource);
        } else {
            $this->requiresEmbeddedSignup = true;
        }

        $this->accountCreated = true;
        $this->selectedProvider = $providerType;

        Notification::make()
            ->title('Account & Provider Configured')
            ->body($providerEnum === WhatsAppProviderTypeEnum::WABA
                ? 'WABA provider selected. Continue to set up your WhatsApp Business account.'
                : 'Twilio provider selected. Continue to configure your WhatsApp number.')
            ->success()
            ->send();
    }

    /**
     * Process Twilio account setup via provider interface.
     */
    public function processTwilioAccountSetup(): void
    {
        $gymId = $this->getGymId();
        $gym = $this->getGym();
        $user = Auth::user();
        $data = $this->data;

        $providerType = 'twilio';
        $providerEnum = WhatsAppProviderTypeEnum::TWILIO;

        $accountData = [
            'gym_id' => $gymId,
            'business_name' => $data['business_name'] ?? $gym?->name ?? $user?->name . ' Gym',
            'business_email' => $data['business_email'] ?? $user?->email,
            'business_description' => $data['business_description'] ?? '',
            'provider_type' => $providerEnum->value,
            'status' => WhatsAppAccountStatusEnum::PENDING,
        ];

        $existingAccount = WhatsAppAccount::where('gym_id', $gymId)->first();

        if ($existingAccount) {
            $existingAccount->update($accountData);
            $this->whatsappAccount = $existingAccount->fresh();
        } else {
            $this->whatsappAccount = WhatsAppAccount::create($accountData);
        }

        $result = $this->getProvider()->createSubAccount(
            $gymId,
            $this->whatsappAccount->business_name ?? $gym?->name,
            $this->whatsappAccount->business_email ?? $user?->email,
        );

        if (! $result['success']) {
            throw new \Exception('Failed to create Twilio subaccount: ' . ($result['error'] ?? 'Unknown error'));
        }

        $this->whatsappAccount->refresh();
        $this->accountCreated = true;
        $this->selectedProvider = $providerType;

        Notification::make()
            ->title('Twilio Account Created')
            ->body('Your Twilio subaccount has been created. Continue to add a phone number.')
            ->success()
            ->send();
    }

    /**
     * Process WABA setup via provider interface.
     */
    public function processWabaSetup(): void
    {
        $gymId = $this->getGymId();
        $gym = $this->getGym();
        $user = Auth::user();
        $data = $this->data;

        $wabaMethod = $data['waba_connection_method'] ?? 'embedded_signup';

        $accountData = [
            'gym_id' => $gymId,
            'business_name' => $data['business_name'] ?? $gym?->name ?? $user?->name . ' Gym',
            'business_email' => $data['business_email'] ?? $user?->email,
            'business_description' => $data['business_description'] ?? '',
            'provider_type' => WhatsAppProviderTypeEnum::WABA->value,
            'status' => WhatsAppAccountStatusEnum::PENDING,
        ];

        $existingAccount = WhatsAppAccount::where('gym_id', $gymId)->first();

        if ($existingAccount) {
            $existingAccount->update($accountData);
            $this->whatsappAccount = $existingAccount->fresh();
        } else {
            $this->whatsappAccount = WhatsAppAccount::create($accountData);
        }

        if ($wabaMethod === 'embedded_signup') {
            if ($this->getProvider()->requiresEmbeddedSignup($gymId)) {
                $this->requiresEmbeddedSignup = true;
                $this->embeddedSignupUrl = route('whatsapp.embedded-signup.redirect', ['tenant' => $gym?->slug]);

                Notification::make()
                    ->title('WABA Setup Initiated')
                    ->body('Please complete the Facebook login to connect your WhatsApp Business Account.')
                    ->info()
                    ->send();
            } else {
                $result = $this->getProvider()->createWaba($gymId, [
                    'business_name' => $this->whatsappAccount->business_name ?? $gym?->name,
                    'business_email' => $this->whatsappAccount->business_email ?? $user?->email,
                    'business_description' => $this->whatsappAccount->business_description ?? '',
                ]);

                if (! $result['success']) {
                    throw new \Exception('Failed to create WABA: ' . ($result['error'] ?? 'Unknown error'));
                }

                $this->whatsappAccount->refresh();
                $this->wabaCreated = true;
            }
        } else {
            $this->requiresEmbeddedSignup = true;
        }

        $this->accountCreated = true;
        $this->selectedProvider = 'waba';

        Notification::make()
            ->title('WABA Account Configured')
            ->body($wabaMethod === 'embedded_signup'
                ? 'Continue with the embedded signup flow.'
                : 'Enter your SABA credentials to connect.')
            ->success()
            ->send();
    }

    /**
     * Handle Twilio-specific setup (subaccount + optional phone number).
     */
    private function processTwilioSetup(string $phoneNumberSource): void
    {
        if (empty($this->whatsappAccount->twilio_account_sid)) {
            $result = app(TwilioAccountManager::class)->createSubaccount(
                $this->getGymId(),
                $this->whatsappAccount->business_name ?? $this->getGym()?->name,
                $this->whatsappAccount->business_email ?? Auth::user()?->email,
            );

            if (! $result['success']) {
                throw new \Exception('Failed to create Twilio subaccount: ' . ($result['error'] ?? 'Unknown error'));
            }

            $this->whatsappAccount->refresh();
        }

        if ($phoneNumberSource === 'purchase_twilio') {
            // Phone number purchase will be handled in WhatsApp Business step for Twilio
        }
    }

    public function saveBusinessProfile(): void
    {
        $data = $this->form->getState();

        if ($this->whatsappAccount) {
            $this->whatsappAccount->update([
                'business_name' => $data['business_name'],
                'business_email' => $data['business_email'],
                'business_description' => $data['business_description'] ?? '',
            ]);

            $this->setupStep = 3;

            Notification::make()
                ->title('Profile Saved')
                ->success()
                ->send();
        }
    }

    public function createWaba(): void
    {
        try {
            $gymId = $this->getGymId();
            $gym = $this->getGym();

            $result = app(TwilioAccountManager::class)->createWaba(
                $gymId,
                [
                    'business_name' => $this->whatsappAccount?->business_name ?? $gym?->name,
                    'business_email' => $this->whatsappAccount?->business_email ?? Auth::user()?->email,
                    'business_description' => $this->whatsappAccount?->business_description ?? '',
                ]
            );

            if ($result['success']) {
                $this->whatsappAccount?->refresh();

                if ($result['requires_embedded_signup'] ?? false) {
                    $this->requiresEmbeddedSignup = true;
                    $this->embeddedSignupUrl = app(EmbeddedSignupService::class)->getLoginUrl($gymId);

                    Notification::make()
                        ->title('WABA Setup Initiated')
                        ->body('Please complete the Facebook login to connect your WhatsApp Business Account.')
                        ->info()
                        ->send();
                } else {
                    $this->setupStep = 3;

                    Notification::make()
                        ->title('WABA Created')
                        ->body('Your WhatsApp Business Account has been created.')
                        ->success()
                        ->send();
                }
            } else {
                throw new \Exception($result['error'] ?? 'Failed to create WABA');
            }
        } catch (\Exception $e) {
            Log::error('Failed to create WABA', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Error Creating WABA')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function connectExistingWaba(): void
    {
        $this->form->validate([
            'manual_phone_number' => ['required', 'phone:INTERNATIONAL'],
            'waba_id' => ['required', 'regex:/^\d{15}$/'],
            'waba_phone_number_id' => ['required', 'regex:/^\d{15}$/'],
            'meta_access_token' => 'required|string',
        ], [
            'manual_phone_number.required' => 'Phone number is required',
            'manual_phone_number.phone' => 'Phone number must be a valid phone number',
            'data.manual_phone_number.phone' => 'Phone number must be a valid phone number',
            'waba_id.required' => 'WABA ID is required',
            'waba_id.regex' => 'WABA ID must be exactly 15 digits (e.g., 123456789012345)',
            'waba_phone_number_id.required' => 'Phone Number ID is required',
            'waba_phone_number_id.regex' => 'Phone Number ID must be exactly 15 digits',
            'meta_access_token.required' => 'Meta access token is required',
        ]);

        try {
            $wabaId = $this->data['waba_id'] ?? null;
            $phoneNumber = $this->data['manual_phone_number'] ?? null;
            $metaAccessToken = $this->data['meta_access_token'] ?? null;
            $wabaPhoneNumberId = $this->data['waba_phone_number_id'] ?? null;
            $gymId = $this->getGymId();
            $user = Auth::user();

            $businessData = [
                'business_name' => $this->data['business_name'] ?? $this->getGym()?->name ?? $user?->name . ' Gym',
                'business_email' => $this->data['business_email'] ?? $user?->email,
                'business_description' => $this->data['business_description'] ?? '',
            ];

            $provider = $this->getProvider();
            if (! $provider) {
                throw new \Exception('Provider not found');
            }

            $result = $provider->connectManualWaba(
                $gymId,
                $wabaId,
                $phoneNumber,
                $metaAccessToken,
                $wabaPhoneNumberId,
                $businessData
            );

            if ($result['success']) {
                $this->refresh();
                Notification::make()
                    ->title('WABA Connected')
                    ->body('Your WhatsApp Business Account has been connected successfully.')
                    ->success()
                    ->send();

                $this->setupStep = 3;
            } else {
                throw new \Exception($result['error'] ?? 'Failed to connect WABA');
            }
        } catch (\Exception $e) {
            Log::error('Failed to connect existing WABA', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Connection Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function startEmbeddedSignup(): void
    {
        try {
            $gym = $this->getGym();

            if (! $gym) {
                Notification::make()
                    ->title('Error')
                    ->body('No tenant found.')
                    ->danger()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Redirecting to Facebook')
                ->body('You will be redirected to complete the setup.')
                ->info()
                ->send();

            $this->redirect(route('whatsapp.embedded-signup.redirect', ['tenant' => $gym->slug]));
        } catch (\Exception $e) {
            Log::error('Failed to start embedded signup', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function skipEmbeddedSignup(): void
    {
        $this->requiresEmbeddedSignup = false;
        $this->setupStep = 3;

        Notification::make()
            ->title('Skipped')
            ->body('You can complete the WABA setup later.')
            ->warning()
            ->send();
    }

    public function searchNumbers(): void
    {
        try {
            $data = $this->form->getState();
            $country = $data['search_country'] ?? 'US';

            $this->isSearching = true;

            $result = $this->getProvider()->getAvailableNumbers($country, 'local', 5);

            $this->isSearching = false;

            if (! $result['success'] || empty($result['numbers'])) {
                $this->availableNumbersLoaded = true;
                $this->availableNumbers = [];
                Notification::make()
                    ->title('No Numbers Found')
                    ->body($result['error'] ?? 'No available phone numbers in this country.')
                    ->warning()
                    ->send();
            } else {
                $this->availableNumbersLoaded = true;
                $this->availableNumbers = $result['numbers'];

                Notification::make()
                    ->title('Numbers Found')
                    ->body('Found ' . count($this->availableNumbers) . ' available numbers.')
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            $this->isSearching = false;

            Notification::make()
                ->title('Search Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function selectNumber(string $phoneNumber): void
    {
        $this->selectedNumber = $phoneNumber;
        $this->purchaseNumber();
    }

    public function purchaseNumber(): void
    {
        try {
            if (! $this->selectedNumber) {
                Notification::make()
                    ->title('Select Number')
                    ->body('Please select a phone number first.')
                    ->warning()
                    ->send();

                return;
            }

            $result = $this->getProvider()->purchaseNumber(
                $this->getGymId(),
                $this->selectedNumber
            );

            if ($result['success']) {
                $this->phoneNumber = $this->whatsappAccount?->phoneNumber()->first();
                $this->availableNumbers = [];
                $this->availableNumbersLoaded = false;
                $this->selectedNumber = null;
                $this->numberPurchased = true;
                $this->setupStep = 4;

                Notification::make()
                    ->title('Number Purchased')
                    ->body('Your WhatsApp phone number has been purchased.')
                    ->success()
                    ->send();
            } else {
                throw new \Exception($result['error'] ?? 'Failed to purchase number');
            }
        } catch (\Exception $e) {
            Log::error('Failed to purchase number', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Purchase Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function registerOwnPhoneNumber(): void
    {
        $this->form->validate([
            'own_phone_number' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{1,14}$/',
            ],
        ], [
            'data.own_phone_number.required' => 'Please enter your phone number.',
            'data.own_phone_number.regex' => 'Please enter a valid phone number in E.164 format (e.g., +1234567890).',
        ]);

        $ownNumber = $this->data['own_phone_number'] ?? null;

        if (! $ownNumber) {
            Notification::make()
                ->title('Phone Number Required')
                ->body('Please enter your phone number before registering.')
                ->warning()
                ->send();

            return;
        }

        try {
            if (! $this->whatsappAccount) {
                throw new \Exception('WhatsApp account not found. Please complete the previous steps first.');
            }

            $result = app(TwilioAccountManager::class)->registerOwnNumber($this->getGymId(), $ownNumber);

            if ($result['success']) {
                $this->senderSid = $result['sender_sid'];
                $this->ownNumberRegistered = true;

                Notification::make()
                    ->title('Verification Code Sent')
                    ->body('A verification code has been sent to your phone. Please enter it below.')
                    ->success()
                    ->send();
            } else {
                throw new \Exception($result['error'] ?? 'Failed to register phone number with Twilio.');
            }
        } catch (\Exception $e) {
            Log::error('Failed to register own phone number', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Registration Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw ValidationException::withMessages(['data.own_phone_number' => $e->getMessage()]);
        }
    }

    public function verifyOwnPhoneNumber(): void
    {
        $otpCode = $this->data['otp_code'] ?? '';

        if (empty($otpCode)) {
            throw ValidationException::withMessages(['data.otp_code' => 'Please enter the OTP code.']);
        }

        if (strlen($otpCode) !== 6) {
            throw ValidationException::withMessages(['data.otp_code' => 'OTP must be exactly 6 characters.']);
        }

        try {
            if (! $this->whatsappAccount) {
                throw new \Exception('WhatsApp account not found. Please complete the previous steps first.');
            }

            $result = app(TwilioAccountManager::class)->verifyOwnNumber($this->getGymId(), $this->senderSid, $otpCode);

            if ($result['success']) {
                $this->phoneNumber = $this->whatsappAccount->phoneNumber()->first();
                $this->numberPurchased = true;
                $this->setupStep = 4;

                Notification::make()
                    ->title('Number Verified')
                    ->body('Your phone number has been verified and registered successfully.')
                    ->success()
                    ->send();
            } else {
                throw ValidationException::withMessages(['data.otp_code' => 'Verification failed. Please try again.']);
            }
        } catch (\Exception $e) {
            Log::error('Failed to verify own phone number', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Verification Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resendOwnPhoneOtp(): void
    {
        try {
            if (! $this->whatsappAccount) {
                throw new \Exception('WhatsApp account not found. Please complete the previous steps first.');
            }

            $result = app(TwilioAccountManager::class)->resendOwnNumberOtp($this->getGymId(), $this->senderSid);

            if ($result['success']) {
                Notification::make()
                    ->title('Code Resent')
                    ->body('A new verification code has been sent to your phone.')
                    ->success()
                    ->send();
            } else {
                throw new \Exception($result['error'] ?? 'Failed to resend verification code.');
            }
        } catch (\Exception $e) {
            Log::error('Failed to resend OTP', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Resend Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function claimWelcomeCredits(): void
    {
        try {
            app(WhatsAppBillingService::class)->addWelcomeCredits($this->getGymId());

            $this->creditBalance = WhatsAppMessageCredit::where('gym_id', $this->getGymId())->first();
            $this->welcomeCreditsClaimed = true;

            if ($this->whatsappAccount) {
                $this->whatsappAccount->update(['status' => 'active']);
                $this->whatsappAccount->refresh();
            }

            $this->viewState = 'active';

            Notification::make()
                ->title('Welcome Credits Claimed!')
                ->body('You now have 50 free WhatsApp message credits.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Failed to claim welcome credits', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function sendTestMessage(array $data): void
    {
        try {
            $phoneNumber = $data['test_phone'] ?? null;

            if (! $phoneNumber) {
                throw new \Exception('Phone number is required');
            }

            $result = app(\App\Services\TwilioWhatsAppService::class)->sendTemplateMessage(
                $phoneNumber,
                'welcome_message',
                [
                    '1' => $this->getGym()?->name ?? 'Gym',
                    '2' => Auth::user()?->name ?? 'User',
                    '3' => 'Premium',
                    '4' => now()->addDays(30)->format('F j, Y'),
                ]
            );

            if ($result['success']) {
                Notification::make()
                    ->title('Test Message Sent!')
                    ->body('Check your WhatsApp for the test message.')
                    ->success()
                    ->send();
            } else {
                throw new \Exception($result['error'] ?? 'Failed to send');
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to Send')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refresh(): void
    {
        $this->initializeFormData();
        $this->data = array_merge($this->data, [
            'provider_type' => $this->selectedProvider,
        ]);
    }

    /**
     * Refresh wizard when provider changes.
     */
    public function refreshWizard(): void
    {
        $this->selectedProvider = $this->data['provider_type'] ?? $this->selectedProvider;
        $this->form->fill();
    }

    /**
     * Override to ensure provider_type is always in sync.
     */
    public function updatedProviderType(?string $value): void
    {
        if ($value) {
            $this->selectedProvider = $value;
        }
    }

    /**
     * Centralized error handling for setup wizard validation failures.
     */
    protected function handleSetupError(\Exception $e, string $context): void
    {
        Log::error($context, ['error' => $e->getMessage()]);

        Notification::make()
            ->title('Setup Failed')
            ->body($e->getMessage())
            ->danger()
            ->send();
    }
}
