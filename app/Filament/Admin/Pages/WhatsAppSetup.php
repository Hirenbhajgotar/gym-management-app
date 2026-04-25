<?php

namespace App\Filament\Admin\Pages;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Interfaces\WhatsAppProviderInterface;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Providers\MetaWhatsAppProvider;
use App\Providers\TwilioWhatsAppProvider;
use App\Services\EmbeddedSignupService;
use App\Services\TwilioAccountManager;
use App\Services\WhatsAppBillingService;
use App\Traits\HasTenantAccess;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class WhatsAppSetup extends Page implements HasActions, HasForms
{
    use HasTenantAccess, InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'Setup';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static string $view = 'filament.admin.pages.whats-app-setup';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public ?WhatsAppAccount $whatsappAccount = null;

    public ?WhatsAppPhoneNumber $phoneNumber = null;

    public ?WhatsAppMessageCredit $creditBalance = null;

    public string $viewState = 'loading';

    public int $setupStep = 1;

    public int $totalSteps = 5;

    public array $availableNumbers = [];

    public bool $availableNumbersLoaded = false;

    public ?string $selectedNumber = null;

    public bool $isSearching = false;

    public array $businessProfile = [];

    public array $defaultTemplatesStatus = [];

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

    // Per-tenant provider selection
    public string $selectedProvider = 'waba';

    public bool $hasExistingNumber = false;

    protected ?WhatsAppProviderInterface $provider = null;

    /**
     * Compute whether the WABA is truly connected based on persisted data.
     */
    public function isWabaConnected(): bool
    {
        return $this->whatsappAccount !== null
            && $this->whatsappAccount->waba_id !== null;
    }

    /**
     * Compute whether a phone number is connected based on persisted data.
     */
    public function isPhoneNumberConnected(): bool
    {
        return $this->phoneNumber !== null;
    }

    /**
     * Get the WhatsApp provider instance for the current gym.
     */
    public function getProvider(): ?WhatsAppProviderInterface
    {
        $gymId = $this->getGymId();

        return match ($this->selectedProvider) {
            WhatsAppProviderTypeEnum::TWILIO->value => new TwilioWhatsAppProvider((int) $gymId),
            WhatsAppProviderTypeEnum::WABA->value => new MetaWhatsAppProvider((int) $gymId),
            default => null,
        };
    }

    public function mount(): void
    {
        $this->useIndividualWaba = config('services.twilio.use_individual_waba', false);
        $this->loadWhatsAppData();
        $this->determineViewState();
        $this->initializeFormData();
    }

    private function loadWhatsAppData(): void
    {
        $gymId = $this->getGymId();

        $this->whatsappAccount = WhatsAppAccount::where('gym_id', $gymId)->first();
        $this->phoneNumber = $this->whatsappAccount?->phoneNumber()->first();
        $this->creditBalance = WhatsAppMessageCredit::where('gym_id', $gymId)->first();
        $this->welcomeCreditsClaimed = $this->creditBalance?->welcome_credits_claimed ?? false;
        $this->isExistWhatsAppTemplates = (bool) WhatsAppTemplate::isExistsForGym((int) $gymId);
    }

    private function determineViewState(): void
    {
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

    private function determineSetupStep(): int
    {
        // Step 1: Create Account + Phone Number (combined)
        if (! $this->whatsappAccount?->twilio_account_sid) {
            return 1;
        }

        // Step 2: WhatsApp Business (WABA or Twilio based on provider)
        if (! $this->whatsappAccount?->waba_id) {
            return 2;
        }

        // Step 3: Phone number is now part of step 1
        // Check if phone number is connected (from either provider)
        if (! $this->phoneNumber && $this->whatsappAccount?->provider_type !== WhatsAppProviderTypeEnum::WABA) {
            // For WABA provider, phone number is handled via embedded signup
            // For Twilio provider, we need to check if phone number exists
            return 2; // Stay on WhatsApp Business step until phone number is set
        }

        $this->numberPurchased = true;

        // Step 3 (Templates): Default Templates
        $defaultTemplates = WhatsAppTemplate::where('gym_id', $this->getGymId())
            ->where('is_default', true)
            ->count();

        if ($defaultTemplates === 0) {
            return 3;
        }

        $this->templatesCreated = true;

        // Step 4: Welcome Credits
        if (! $this->welcomeCreditsClaimed) {
            return 4;
        }

        // Step 5: Finish
        return 5;
    }

    private function initializeFormData(): void
    {
        $gym = $this->getGym();
        $user = Auth::user();

        // Preserve existing provider selection if already set (for live toggles)
        // Otherwise, get from DB or use default
        $existingProvider = $this->data['provider_type'] ?? $this->selectedProvider ?? null;
        if ($existingProvider !== null) {
            // Live toggle in progress - preserve current value
            $this->selectedProvider = $existingProvider instanceof WhatsAppProviderTypeEnum
                ? $existingProvider->value
                : $existingProvider;
        } else {
            // Initial load - get from database or default
            $this->selectedProvider = $this->whatsappAccount?->provider_type?->value ?? WhatsAppProviderTypeEnum::TWILIO->value;
        }

        $this->data = [
            'business_name' => $this->whatsappAccount?->business_name ?? $gym?->name ?? '',
            'business_email' => $this->whatsappAccount?->business_email ?? $user?->email ?? '',
            'business_description' => $this->whatsappAccount?->business_description ?? '',
            'search_country' => 'US',
            'waba_connection_method' => 'embedded_signup',
            'waba_id' => $this->whatsappAccount?->waba_id ?? '',
            'waba_phone_number_id' => '',
            'manual_phone_number' => '',
            'meta_access_token' => '',
            'phone_number_source' => 'use_own_number',
            'own_phone_number' => '',
            'otp_code' => '',
            // Per-tenant provider selection - PRESERVE live selection if exists
            'provider_type' => $this->selectedProvider,
            'has_existing_number' => false,
        ];

        $this->businessProfile = [
            'business_name' => $this->whatsappAccount?->business_name ?? $gym?->name ?? '',
            'business_email' => $this->whatsappAccount?->business_email ?? $user?->email ?? '',
            'business_description' => $this->whatsappAccount?->business_description ?? '',
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getWizardStepButtonLabel(int $step = 1): string
    {
        return match ($step) {
            1 => $this->accountCreated ? 'Continue' : 'Create Account & Continue',
            2 => 'Continue',
            3 => 'Continue',
            4 => 'Continue',
            5 => 'Continue',
            6 => 'Finish',
            default => 'Continue',
        };
    }

    protected function getFormSchema(): array
    {
        return match ($this->viewState) {
            'onboarding' => $this->getOnboardingSchema(),
            'setup' => $this->getSetupSchema(),
            'active' => $this->getActiveSchema(),
            'error' => $this->getErrorSchema(),
            default => [],
        };
    }

    private function getOnboardingSchema(): array
    {
        return [
            Forms\Components\Section::make('WhatsApp Business Integration')
                ->description('Connect your gym with members through WhatsApp')
                ->schema([
                    Forms\Components\Placeholder::make('')
                        ->content(new HtmlString('
                            <div class="space-y-6">
                                <!-- Hero Section -->
                                <div class="text-center py-6 border-b border-gray-200 dark:border-gray-700">
                                    <div class="mx-auto w-20 h-20 bg-gradient-to-br from-green-400 to-green-600 rounded-2xl flex items-center justify-center mb-4 shadow-lg">
                                        <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                        </svg>
                                    </div>
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Welcome to WhatsApp Business</h2>
                                    <p class="text-gray-600 dark:text-gray-400 max-w-xl mx-auto">Set up your dedicated WhatsApp Business account to engage with your gym members through automated notifications, reminders, and personalized messages.</p>
                                </div>

                                <!-- Features Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 py-4">
                                    <div class="text-center p-4 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                                        <div class="w-10 h-10 mx-auto bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center mb-3">
                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                            </svg>
                                        </div>
                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm mb-1">Automated Notifications</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Membership reminders, payment alerts, and attendance notifications</p>
                                    </div>
                                    <div class="text-center p-4 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                                        <div class="w-10 h-10 mx-auto bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center mb-3">
                                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm mb-1">Class Reminders</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Send workout reminders and schedule updates instantly</p>
                                    </div>
                                    <div class="text-center p-4 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                                        <div class="w-10 h-10 mx-auto bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center mb-3">
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                        </div>
                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm mb-1">Member Engagement</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Birthday wishes, promotional offers, and personalized messages</p>
                                    </div>
                                </div>
                            </div>
                        ')),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('start_setup')
                            ->label('Start Setup')
                            ->color('primary')
                            ->size('lg')
                            ->icon('heroicon-o-rocket-launch')
                            ->action('startSetup'),
                    ])->alignCenter(),
                ]),
        ];
    }

    private function getSetupSchema(): array
    {
        // Always evaluate fresh - use current selectedProvider
        return [
            Forms\Components\Wizard::make([
                $this->getProviderSelectionStep(), // Step 1: Select Provider (Twilio or WABA)
                $this->getAccountStep(), // Step 2: Account setup (provider-specific)
                $this->getPhoneNumberStep(), // Step 3: Phone number setup (provider-specific)
                $this->getTemplatesStep(), // Step 4: Default Templates
                $this->getWelcomeCreditsStep(), // Step 5: Welcome Credits
            ])
                ->skippable(true)
                ->startOnStep($this->setupStep)
                ->nextAction(function (Action $action) {
                    $step = (int) request()->get('step', $this->setupStep);
                    $action
                        ->label($this->getWizardStepButtonLabel($step))
                        ->icon('heroicon-o-arrow-right');
                })
                ->previousAction(function (Action $action) {
                    $action->label('Back')->icon('heroicon-o-arrow-left');
                }),
        ];
    }

    /**
     * Step 1: Provider Selection - First step to select Twilio or WABA.
     */
    private function getProviderSelectionStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Select Provider')
            ->label('Select Provider')
            ->schema([
                Forms\Components\Section::make('Choose Your WhatsApp Provider')
                    ->description('Select how you want to set up WhatsApp messaging')
                    ->schema([
                        Forms\Components\Placeholder::make('')
                            ->content(fn () => new HtmlString('
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4">
                                    <div class="p-4 rounded-lg border-2 ' . ($this->selectedProvider === WhatsAppProviderTypeEnum::WABA->value ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700') . '">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">Meta WABA</span>
                                            ' . ($this->selectedProvider === WhatsAppProviderTypeEnum::WABA->value ? '<span class="text-green-600">✓ Selected</span>' : '') . '
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Connect your existing WhatsApp Business number using Meta\'s embedded signup.</p>
                                        <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                                            <li>• Use your own number</li>
                                            <li>• Embedded signup flow</li>
                                            <li>• Lower costs</li>
                                        </ul>
                                    </div>
                                    <div class="p-4 rounded-lg border-2 ' . ($this->selectedProvider === WhatsAppProviderTypeEnum::TWILIO->value ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700') . '">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">Twilio</span>
                                            ' . ($this->selectedProvider === WhatsAppProviderTypeEnum::TWILIO->value ? '<span class="text-green-600">✓ Selected</span>' : '') . '
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Purchase a dedicated WhatsApp number through Twilio. We\'ll create a subaccount for your gym.</p>
                                        <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                                            <li>• Dedicated phone number</li>
                                            <li>• Full WhatsApp API access</li>
                                            <li>• Subaccount isolation</li>
                                        </ul>
                                    </div>
                                </div>
                            ')),
                        Forms\Components\Radio::make('provider_type')
                            ->label('')
                            ->options(WhatsAppProviderTypeEnum::class)
                            ->descriptions(WhatsAppProviderTypeEnum::toDescriptionsArray())
                            ->default(WhatsAppProviderTypeEnum::TWILIO->value)
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                // Convert enum to string value
                                $stringValue = $state instanceof \App\Enums\WhatsAppProviderTypeEnum
                                    ? $state->value
                                    : (string) $state;

                                $this->selectedProvider = $stringValue;
                                $this->data['provider_type'] = $stringValue;

                                // Force form to re-render with explicit data array
                                $this->form->fill($this->data);
                            })
                            ->disabled($this->accountCreated),
                    ])
                    ->visible(fn () => ! $this->accountCreated || ! $this->whatsappAccount?->hasProvider()),
            ])
            ->afterValidation(function () {
                // Provider selection is validated, store the provider type
                $data = $this->form->getState();
                $providerState = $data['provider_type'] ?? null;
                // Convert enum to string value if needed
                $this->selectedProvider = $providerState instanceof \App\Enums\WhatsAppProviderTypeEnum
                    ? $providerState->value
                    : (string) ($providerState ?? WhatsAppProviderTypeEnum::WABA->value);
            });
    }

    /**
     * Step 2: Account setup - Provider-specific account configuration.
     */
    private function getAccountStep(): Forms\Components\Wizard\Step
    {
        // Use data['provider_type'] from form state if available (live selection)
        $formProvider = $this->data['provider_type'] ?? $this->selectedProvider;
        // Ensure we have string value
        $providerValue = $formProvider instanceof WhatsAppProviderTypeEnum
            ? $formProvider->value
            : (string) $formProvider;

        // Route to provider-specific account schema
        return $providerValue === WhatsAppProviderTypeEnum::WABA->value
            ? $this->getWabaAccountStep()
            : $this->getTwilioAccountStep();
    }

    /**
     * Step 3: Phone number setup - Provider-specific phone number configuration.
     */
    private function getPhoneNumberStep(): Forms\Components\Wizard\Step
    {
        // Use data['provider_type'] from form state if available (live selection)
        $formProvider = $this->data['provider_type'] ?? $this->selectedProvider;
        // Ensure we have string value
        $providerValue = $formProvider instanceof WhatsAppProviderTypeEnum
            ? $formProvider->value
            : (string) $formProvider;

        // Route to provider-specific phone number schema
        return $providerValue === WhatsAppProviderTypeEnum::WABA->value
            ? $this->getWabaPhoneNumberStep()
            : $this->getTwilioPhoneNumberStep();
    }

    private function getCreateAccountStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Create Account')
            ->label('Account & Phone')
            ->schema([
                // Business Info Section
                Forms\Components\Section::make('Business Information')
                    ->description('Enter your business details')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('business_name')
                                ->label('Business Name')
                                ->required()
                                ->disabled($this->accountCreated),
                            Forms\Components\TextInput::make('business_email')
                                ->label('Business Email')
                                ->email()
                                ->required()
                                ->disabled($this->accountCreated),
                        ]),
                        Forms\Components\Textarea::make('business_description')
                            ->label('Business Description')
                            ->rows(2)
                            ->placeholder('Describe your gym business...'),
                    ]),

                // Provider Selection Section (Per-Tenant)
                Forms\Components\Section::make('WhatsApp Provider')
                    ->description('Choose how you want to set up WhatsApp')
                    ->schema([
                        Forms\Components\Radio::make('provider_type')
                            ->label('Select your provider')
                            ->options([
                                'twilio' => 'Twilio (Purchase a number)',
                                'waba' => 'Meta WABA (Use existing number)',
                            ])
                            ->descriptions([
                                'twilio' => 'Purchase a dedicated WhatsApp number through Twilio. We\'ll create a subaccount for you.',
                                'waba' => 'Connect your existing WhatsApp Business number. Uses Meta\'s embedded signup flow.',
                            ])
                            ->default('twilio')
                            ->live()
                            ->disabled($this->accountCreated),

                        // Show phone number options based on provider
                        Forms\Components\Section::make('Phone Number')
                            ->description('Choose how to get your WhatsApp phone number')
                            ->schema([
                                Forms\Components\Radio::make('phone_number_source')
                                    ->label('How would you like to get your phone number?')
                                    ->options([
                                        'purchase_twilio' => 'Purchase from Twilio',
                                        'use_own_number' => 'Use My Own Number',
                                    ])
                                    ->descriptions([
                                        'purchase_twilio' => 'Get a new dedicated WhatsApp number instantly.',
                                        'use_own_number' => 'Register an existing phone number you own.',
                                    ])
                                    ->default('purchase_twilio')
                                    ->live()
                                    ->visible(fn (Get $get) => $get('provider_type') === 'twilio' && ! $this->numberPurchased),
                            ])
                            ->visible(fn (Get $get) => $get('provider_type') === 'twilio'),
                    ])
                    ->visible(fn () => ! $this->accountCreated || ! $this->whatsappAccount?->hasProvider()),
            ])
            ->afterValidation(function () {
                try {
                    $this->processAccountAndProviderSetup();
                } catch (\Exception $e) {
                    Log::error('Failed to process account setup', ['error' => $e->getMessage()]);

                    Notification::make()
                        ->title('Setup Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    throw new Halt;
                }
            });
    }

    /**
     * Twilio Account Step - Step 2 for Twilio provider.
     */
    private function getTwilioAccountStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Twilio Account')
            ->label('Twilio Account')
            ->schema([
                Forms\Components\Section::make('Business Information')
                    ->description('Enter your business details for the Twilio subaccount')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('business_name')
                                ->label('Business Name')
                                ->required()
                                ->disabled($this->accountCreated),
                            Forms\Components\TextInput::make('business_email')
                                ->label('Business Email')
                                ->email()
                                ->required()
                                ->disabled($this->accountCreated),
                        ]),
                        Forms\Components\Textarea::make('business_description')
                            ->label('Business Description')
                            ->rows(2)
                            ->placeholder('Describe your gym business...'),
                    ]),
            ])
            ->afterValidation(function () {
                try {
                    $this->processTwilioAccountSetup();
                } catch (\Exception $e) {
                    Log::error('Failed to process Twilio account setup', ['error' => $e->getMessage()]);
                    Notification::make()
                        ->title('Setup Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                    throw new Halt;
                }
            });
    }

    /**
     * Twilio Phone Number Step - Step 3 for Twilio provider.
     */
    private function getTwilioPhoneNumberStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Phone Number')
            ->label('Phone Number')
            ->schema([
                Forms\Components\Section::make('Get a Phone Number')
                    ->description('Choose how to get your WhatsApp phone number')
                    ->schema([
                        Forms\Components\Radio::make('phone_number_source')
                            ->label('How would you like to get your phone number?')
                            ->options([
                                'purchase_twilio' => 'Purchase from Twilio',
                                'use_own_number' => 'Use My Own Number',
                            ])
                            ->descriptions([
                                'purchase_twilio' => 'Get a new dedicated WhatsApp number instantly.',
                                'use_own_number' => 'Register an existing phone number you own.',
                            ])
                            ->default('purchase_twilio')
                            ->live()
                            ->visible(fn (Get $get) => ! $this->numberPurchased),
                    ])
                    ->visible(fn () => ! $this->numberPurchased),
            ])
            ->afterValidation(function () {
                // Handled by separate actions
            });
    }

    /**
     * WABA Account Step - Step 2 for WABA provider.
     */
    private function getWabaAccountStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('WhatsApp Business')
            ->label('WhatsApp Business')
            ->schema([
                Forms\Components\Section::make('Connect Your WhatsApp Business')
                    ->description('Set up your Meta WhatsApp Business Account')
                    ->schema([
                        Forms\Components\Radio::make('waba_connection_method')
                            ->label('Connection Method')
                            ->options([
                                'embedded_signup' => 'Embedded Signup (Recommended)',
                                'manual_connect' => 'Manual Connect',
                            ])
                            ->descriptions([
                                'embedded_signup' => 'Use Meta\'s embedded signup flow to create and connect your WABA.',
                                'manual_connect' => 'Enter your existing WABA credentials manually.',
                            ])
                            ->default('embedded_signup')
                            ->live()
                            ->visible(fn () => ! $this->isWabaConnected()),

                        // Embedded Signup Button (shown when embedded_signup is selected)
                        Forms\Components\Placeholder::make('')
                            ->content(new HtmlString('
                                <div class="mt-4 p-4 bg-blue-50 dark:bg-gray-800 border border-blue-200 dark:border-blue-400 rounded-lg">
                                    <h4 class="font-medium text-blue-800 dark:text-blue-400 mb-2">Complete WABA Setup</h4>
                                    <p class="text-sm text-blue-700 dark:text-blue-300 mb-4">Click the button below to connect your WhatsApp Business Account through Facebook.</p>
                                </div>
                            '))
                            ->visible(fn (Get $get): bool => $get('waba_connection_method') === 'embedded_signup' && ! $this->isWabaConnected()),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('connectViaFacebook')
                                ->label('Connect via Facebook')
                                ->color('success')
                                ->icon('heroicon-o-arrow-right')
                                ->action('startEmbeddedSignup')
                                ->visible(fn (Get $get): bool => $get('waba_connection_method') === 'embedded_signup' && ! $this->isWabaConnected())
                                ->extraAttributes([
                                    'wire:loading.attr' => 'disabled',
                                    'wire:target' => 'data.waba_connection_method',
                                ]),
                        ]),

                        // Manual Connect Form Fields (shown when manual_connect is selected)
                        Forms\Components\Section::make('Connect Your Existing WABA')
                            ->description('Enter your WhatsApp Business Account details from Meta Business Manager.')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('waba_id')
                                        ->label('WABA ID')
                                        ->helperText('Your WhatsApp Business Account ID from Meta Business Manager (15 digits)')
                                        ->required()
                                        ->placeholder('123456789012345')
                                        ->regex('/^\d{15}$/')
                                        ->validationMessages([
                                            'required' => 'WABA ID is required',
                                            'regex' => 'WABA ID must be exactly 15 digits (e.g., 123456789012345)',
                                        ]),

                                    Forms\Components\TextInput::make('manual_phone_number')
                                        ->label('WhatsApp Phone Number')
                                        ->tel()
                                        ->helperText('The phone number associated with your WABA (e.g., +1234567890)')
                                        ->required()
                                        ->placeholder('+1234567890')
                                        ->rule('phone:INTERNATIONAL')
                                        ->validationMessages([
                                            'required' => 'Phone number is required',
                                            'phone' => 'Phone number must be a valid phone number',
                                        ]),
                                ]),

                                Forms\Components\TextInput::make('waba_phone_number_id')
                                    ->label('WhatsApp Phone Number ID')
                                    ->helperText('The Phone Number ID from Meta Business Manager (15 digits, e.g., 123456789012345)')
                                    ->required()
                                    ->placeholder('123456789012345')
                                    ->regex('/^\d{15}$/')
                                    ->validationMessages([
                                        'required' => 'Phone Number ID is required',
                                        'regex' => 'Phone Number ID must be exactly 15 digits',
                                    ]),

                                Forms\Components\TextInput::make('meta_access_token')
                                    ->label('Meta Access Token')
                                    ->helperText('Required. Permanent access token from Meta Business Manager to verify your account.')
                                    ->required()
                                    ->password(),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('connectExistingWaba')
                                        ->label('Connect WABA')
                                        ->color('primary')
                                        ->icon('heroicon-o-link')
                                        ->action('connectExistingWaba')
                                        ->extraAttributes([
                                            'wire:loading.attr' => 'disabled',
                                            'wire:target' => 'data.waba_connection_method',
                                        ]),
                                ]),
                            ])
                            ->visible(fn (Get $get): bool => $get('waba_connection_method') === 'manual_connect' && ! $this->isWabaConnected())
                            ->collapsed(false),

                        Forms\Components\Section::make('How to Find Your WABA Details')
                            ->schema([
                                Forms\Components\Placeholder::make('waba_instructions')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('
                                        <div class="prose prose-sm dark:prose-invert">
                                            <ol>
                                                <li>Go to <a href="https://business.facebook.com" target="_blank" class="text-primary-600">Meta Business Manager</a></li>
                                                <li>Navigate to <strong>Business Settings</strong> &rarr; <strong>Accounts</strong> &rarr; <strong>WhatsApp Accounts</strong></li>
                                                <li>Select your WhatsApp Business Account</li>
                                                <li>Copy the <strong>WhatsApp Business Account ID</strong> (WABA ID)</li>
                                                <li>Go to <strong>Phone Numbers</strong> to find your phone number</li>
                                                <li>For the Access Token, go to <strong>System Users</strong> &rarr; Create a token with WhatsApp permissions</li>
                                            </ol>
                                        </div>
                                    ')),
                            ])
                            ->collapsible()
                            ->collapsed(true)
                            ->visible(fn (Get $get): bool => $get('waba_connection_method') === 'manual_connect' && ! $this->isWabaConnected()),
                    ])
                    ->visible(fn () => ! $this->isWabaConnected()),

                // Show success if already connected
                Forms\Components\Placeholder::make('waba_connected')
                    ->hiddenLabel()
                    ->content(new HtmlString('
                        <div class="flex flex-col items-center justify-center py-8 px-4 text-center">
                            <div class="w-20 h-20 mb-4 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">WhatsApp Business Connected</h3>
                            <p class="text-gray-500 dark:text-gray-400">Your WhatsApp Business Account is connected.</p>
                        </div>
                    '))
                    ->visible(fn () => $this->isWabaConnected()),
            ])
            ->afterValidation(function () {
                try {
                    $this->processWabaSetup();
                } catch (\Exception $e) {
                    Log::error('Failed to process WABA setup', ['error' => $e->getMessage()]);
                    Notification::make()
                        ->title('Setup Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                    throw new Halt;
                }
            });
    }

    /**
     * WABA Phone Number Step - Step 3 for WABA provider.
     */
    private function getWabaPhoneNumberStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Phone Number')
            ->label('Phone Number')
            ->schema([
                Forms\Components\Placeholder::make('phone_info')
                    ->hiddenLabel()
                    ->content(new HtmlString('
                        <div class="py-4">
                            <p class="text-gray-600 dark:text-gray-400">Your phone number will be handled through the WhatsApp Business Account connected in the previous step.</p>
                        </div>
                    ')),
            ])
            ->visible(fn (Get $get): bool => $get('provider_type') === 'twilio' && ! $this->isWabaConnected());
    }

    private function getTemplatesStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Templates')
            ->label('Default Templates')
            ->schema([
                Forms\Components\Placeholder::make('')
                    ->content(new HtmlString('
                        <div class="mb-4">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">Setting up default message templates</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">We will create 8 utility templates for common gym communications. These will be submitted for Meta approval.</p>
                        </div>
                    ')),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('createTemplates')
                        ->label('Create Templates')
                        ->color('primary')
                        ->url(route('filament.admin.resources.whats-app-templates.index', ['tenant' => $this->getTenantSlug()]))
                        ->openUrlInNewTab(true)
                        ->icon('heroicon-o-check-circle'),
                ]),
            ])
            ->afterValidation(function () {
                $this->setupStep = 5;
            })
            ->visible(fn () => ! $this->isExistWhatsAppTemplates);
    }

    private function getWelcomeCreditsStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Welcome Credits')
            ->label('Welcome Credits')
            ->schema([
                Forms\Components\Placeholder::make('')
                    ->content(new HtmlString('
                        <div class="text-center py-6">
                            <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mb-4">
                                <span class="text-2xl">🎁</span>
                            </div>
                            <h3 class="font-medium text-lg text-gray-900 dark:text-gray-100 mb-2">50 Free Welcome Credits!</h3>
                            <p class="text-gray-500 dark:text-gray-400">Claim your 50 free WhatsApp message credits to get started.</p>
                        </div>
                    ')),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('claimWelcome')
                        ->label($this->welcomeCreditsClaimed ? 'Credits Claimed' : 'Claim Welcome Credits')
                        ->color('success')
                        ->action('claimWelcomeCredits')
                        ->disabled($this->welcomeCreditsClaimed)
                        ->icon($this->welcomeCreditsClaimed ? 'heroicon-o-check-circle' : null)
                        ->visible(fn () => ! $this->welcomeCreditsClaimed),
                ])->alignCenter(),
            ])
            ->afterValidation(function () {
                $this->setupStep = 6;
            });
    }

    private function getActiveSchema(): array
    {
        return [
            Forms\Components\Section::make('WhatsApp Business Status')
                ->description('Your WhatsApp Business integration is active.')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Placeholder::make('status')
                            ->label('Account Status')
                            ->content(new HtmlString('
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200">
                                    ✓ Active
                                </span>
                            ')),

                        Forms\Components\Placeholder::make('phone_number')
                            ->label('WhatsApp Number')
                            ->content($this->phoneNumber?->phone_number ?? 'N/A'),

                        Forms\Components\Placeholder::make('account_created')
                            ->label('Account Created')
                            ->content($this->whatsappAccount?->created_at?->format('M d, Y g:i A') ?? 'N/A'),

                        Forms\Components\Placeholder::make('credits')
                            ->label('Message Credits')
                            ->content(($this->creditBalance?->remaining_credits ?? 0) . ' remaining'),
                    ]),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('testMessage')
                            ->label('Send Test Message')
                            ->color('info')
                            ->form([
                                PhoneInput::make('test_phone')
                                    ->label('Phone Number')
                                    ->required(),
                            ])
                            ->action('sendTestMessage'),

                        Forms\Components\Actions\Action::make('viewTemplates')
                            ->label('Manage Templates')
                            ->url(route('filament.admin.resources.whats-app-templates.index', ['tenant' => $this->getTenantSlug()])),
                    ]),
                ]),
        ];
    }

    private function getErrorSchema(): array
    {
        return [
            Forms\Components\Section::make('Setup Incomplete')
                ->description('There was an issue with your WhatsApp setup.')
                ->schema([
                    Forms\Components\Placeholder::make('error_message')
                        ->content(new HtmlString('
                            <div class="text-center py-8">
                                <div class="mx-auto w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Setup Incomplete</h3>
                                <p class="text-gray-500 dark:text-gray-400 mb-4">Please contact support or try setting up again.</p>
                            </div>
                        ')),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('retry')
                            ->label('Retry Setup')
                            ->color('primary')
                            ->action('retrySetup'),
                    ])->alignCenter(),
                ]),
        ];
    }

    public function startSetup(): void
    {
        $this->viewState = 'setup';
        $this->setupStep = 1;
    }

    public function retrySetup(): void
    {
        $this->loadWhatsAppData();
        $this->determineViewState();
    }

    public function createSubaccount(): void
    {
        // Legacy method - redirect to new provider-based method
        $this->processTwilioAccountSetup();
    }

    /**
     * Process account creation and provider selection in combined step.
     * This handles both Twilio (subaccount creation) and WABA (embedded signup) flows.
     */
    public function processAccountAndProviderSetup(): void
    {
        $gymId = $this->getGymId();
        $gym = $this->getGym();
        $user = Auth::user();
        $data = $this->data;

        $providerType = $data['provider_type'] ?? 'twilio';
        $phoneNumberSource = $data['phone_number_source'] ?? 'purchase_twilio';

        // Determine provider enum
        $providerEnum = $providerType === 'waba'
            ? WhatsAppProviderTypeEnum::WABA
            : WhatsAppProviderTypeEnum::TWILIO;

        // Create base account record with provider type
        $accountData = [
            'gym_id' => $gymId,
            'business_name' => $data['business_name'] ?? $gym?->name ?? $user?->name . ' Gym',
            'business_email' => $data['business_email'] ?? $user?->email,
            'business_description' => $data['business_description'] ?? '',
            'provider_type' => $providerEnum->value,
            'status' => WhatsAppAccountStatusEnum::PENDING,
        ];

        // Check if account already exists
        $existingAccount = WhatsAppAccount::where('gym_id', $gymId)->first();

        if ($existingAccount) {
            // Update existing with provider type
            $existingAccount->update($accountData);
            $this->whatsappAccount = $existingAccount->fresh();
        } else {
            // Create new account
            $this->whatsappAccount = WhatsAppAccount::create($accountData);
        }

        // Handle provider-specific setup
        if ($providerEnum === WhatsAppProviderTypeEnum::TWILIO) {
            // Twilio: Create subaccount and optionally purchase phone number
            $this->processTwilioSetup($phoneNumberSource);
        } else {
            // WABA: Set up for embedded signup (phone number handled in next step)
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
     * Called from getTwilioAccountStep() after validation.
     */
    public function processTwilioAccountSetup(): void
    {
        $gymId = $this->getGymId();
        $gym = $this->getGym();
        $user = Auth::user();
        $data = $this->data;

        $providerType = 'twilio';
        $providerEnum = WhatsAppProviderTypeEnum::TWILIO;

        // Create or update WhatsAppAccount
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

        // Use provider interface for subaccount creation (T5)
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
     * Called from getWabaAccountStep() after validation.
     */
    public function processWabaSetup(): void
    {
        $gymId = $this->getGymId();
        $gym = $this->getGym();
        $user = Auth::user();
        $data = $this->data;

        $wabaMethod = $data['waba_connection_method'] ?? 'embedded_signup';

        // Create or get WhatsAppAccount with WABA provider type
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
            // Use embedded signup - check if provider requires it
            if ($this->getProvider()->requiresEmbeddedSignup($gymId)) {
                $this->requiresEmbeddedSignup = true;
                $this->embeddedSignupUrl = route('whatsapp.embedded-signup.redirect', ['tenant' => $gym?->slug]);

                Notification::make()
                    ->title('WABA Setup Initiated')
                    ->body('Please complete the Facebook login to connect your WhatsApp Business Account.')
                    ->info()
                    ->send();
            } else {
                // Create WABA via provider
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
            // Manual connect - handled separately via action
            // For now, mark as requiring setup
            $this->requiresEmbeddedSignup = true;
        }

        $this->accountCreated = true;
        $this->selectedProvider = 'waba';

        Notification::make()
            ->title('WABA Account Configured')
            ->body($wabaMethod === 'embedded_signup'
                ? 'Continue with the embedded signup flow.'
                : 'Enter your WABA credentials to connect.')
            ->success()
            ->send();
    }

    /**
     * Handle Twilio-specific setup (subaccount + optional phone number).
     */
    private function processTwilioSetup(string $phoneNumberSource): void
    {
        // Create Twilio subaccount if not exists
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

        // If user wants to purchase a number now, trigger the flow
        if ($phoneNumberSource === 'purchase_twilio') {
            // Set up for phone number step in Step 2
            // (Phone number purchase will be handled in WhatsApp Business step for Twilio)
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
                    // WABA created and persisted - isWabaConnected() will now return true
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

            // Build business data - provider now handles verification internally
            $businessData = [
                'business_name' => $this->data['business_name'] ?? $this->getGym()?->name ?? $user?->name . ' Gym',
                'business_email' => $this->data['business_email'] ?? $user?->email,
                'business_description' => $this->data['business_description'] ?? '',
            ];

            // Call provider - it handles verification, account creation, and phone number setup
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
        // Don't set wabaCreated = true here - the success state should only show
        // when WABA is truly connected (persisted waba_id check in isWabaConnected())
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

            // Use provider interface (T6)
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

            // Use provider interface (T7)
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

                // $this->redirectRoute('filament.admin.pages.whats-app-setup');
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

        // Validate phone number format (E.164: + followed by 1-15 digits)
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

            // $this->redirectRoute('filament.admin.pages.whats-app-setup');
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
        // Reinitialize form data with updated provider selection
        $this->initializeFormData();
        // Force form to refresh by resetting data
        $this->data = array_merge($this->data, [
            'provider_type' => $this->selectedProvider,
        ]);
    }

    /**
     * Refresh wizard when provider changes.
     */
    public function refreshWizard(): void
    {
        // Sync provider from form data
        $this->selectedProvider = $this->data['provider_type'] ?? $this->selectedProvider;
        // Refresh the form schema
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
}
