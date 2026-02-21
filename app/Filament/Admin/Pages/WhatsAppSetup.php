<?php

namespace App\Filament\Admin\Pages;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Services\EmbeddedSignupService;
use App\Services\TwilioAccountManager;
use App\Services\WhatsAppBillingService;
use App\Services\WhatsAppTemplateService;
use App\Traits\HasTenantAccess;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
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

    public int $totalSteps = 6;

    public array $availableNumbers = [];

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

    public bool $templatesCreated = false;

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
    }

    private function determineViewState(): void
    {
        if (! $this->whatsappAccount) {
            $this->viewState = 'onboarding';
            $this->setupStep = 1;

            return;
        }

        $this->accountCreated = true;

        if ($this->whatsappAccount->status === 'pending') {
            $this->viewState = 'setup';
            $this->setupStep = $this->determineSetupStep();

            return;
        }

        if ($this->whatsappAccount->status === 'active' && $this->phoneNumber) {
            $this->viewState = 'active';

            return;
        }

        $this->viewState = 'error';
    }

    private function determineSetupStep(): int
    {
        if (! $this->whatsappAccount->waba_id) {
            return 2;
        }

        $this->wabaCreated = true;

        if (! $this->phoneNumber) {
            return 3;
        }

        $this->numberPurchased = true;

        $defaultTemplates = WhatsAppTemplate::where('gym_id', $this->getGymId())
            ->where('is_default', true)
            ->count();

        if ($defaultTemplates === 0) {
            return 4;
        }

        $this->templatesCreated = true;

        if (! $this->welcomeCreditsClaimed) {
            return 5;
        }

        return 6;
    }

    private function initializeFormData(): void
    {
        $gym = $this->getGym();
        $user = Auth::user();

        $this->data = [
            'gym_name' => $gym?->name ?? '',
            'owner_email' => $user?->email ?? '',
            'business_description' => '',
            'search_country' => 'US',
        ];

        $this->businessProfile = [
            'business_name' => $gym?->name ?? '',
            'business_email' => $user?->email ?? '',
            'business_description' => $gym?->description ?? '',
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
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
        return [
            Forms\Components\Wizard::make([
                $this->getCreateAccountStep(),
                $this->getWabaSetupStep(),
                $this->getPhoneNumberStep(),
                $this->getTemplatesStep(),
                $this->getWelcomeCreditsStep(),
            ])
                ->skippable(true)
                ->startOnStep($this->setupStep),
        ];
    }

    private function getCreateAccountStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Create Account')
            ->label('1. Create Account')
            ->schema([
                Forms\Components\Placeholder::make('step1_info')
                    ->content(new HtmlString('
                        <div class="mb-4">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">Create Your WhatsApp Business Account</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Enter your business details to set up your dedicated WhatsApp messaging account.</p>
                        </div>
                    ')),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('business_name')
                        ->label('Business Name')
                        ->required()
                        ->default($this->getGym()?->name)
                        ->disabled($this->accountCreated),
                    Forms\Components\TextInput::make('business_email')
                        ->label('Business Email')
                        ->email()
                        ->required()
                        ->default(Auth::user()?->email)
                        ->disabled($this->accountCreated),
                ]),
                Forms\Components\Textarea::make('business_description')
                    ->label('Business Description')
                    ->rows(3)
                    ->placeholder('Describe your gym business...')
                    ->disabled($this->accountCreated),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('createAccount')
                        ->label($this->accountCreated ? 'Account Created' : 'Create Account')
                        ->color('primary')
                        ->action('createSubaccount')
                        ->disabled($this->accountCreated)
                        ->icon($this->accountCreated ? 'heroicon-o-check-circle' : null),
                ]),
            ]);
    }

    private function getWabaSetupStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('WABA Setup')
            ->label('2. WhatsApp Business')
            ->schema([
                Forms\Components\Placeholder::make('waba_info')
                    ->content(new HtmlString('
                        <div class="mb-4">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">Creating WhatsApp Business Account (WABA)</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">This is required to send WhatsApp messages to your members.</p>
                        </div>
                    ')),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('createWaba')
                        ->label($this->wabaCreated ? 'WABA Created' : ($this->useIndividualWaba ? 'Create Individual WABA' : 'Create Shared WABA'))
                        ->color('primary')
                        ->action('createWaba')
                        ->disabled($this->wabaCreated)
                        ->icon($this->wabaCreated ? 'heroicon-o-check-circle' : null),
                ]),
                Forms\Components\Placeholder::make('embedded_signup_info')
                    ->hidden(! $this->requiresEmbeddedSignup)
                    ->content(new HtmlString('
                        <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">Complete WABA Setup</h4>
                            <p class="text-sm text-blue-700 dark:text-blue-300 mb-4">Click the button below to connect your WhatsApp Business Account through Facebook.</p>
                        </div>
                    ')),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('startEmbeddedSignup')
                        ->label('Connect via Facebook')
                        ->color('success')
                        ->icon('heroicon-o-arrow-path')
                        ->action('startEmbeddedSignup')
                        ->hidden(! $this->requiresEmbeddedSignup),
                    Forms\Components\Actions\Action::make('skipEmbeddedSignup')
                        ->label('Skip for Now')
                        ->color('gray')
                        ->action('skipEmbeddedSignup')
                        ->hidden(! $this->requiresEmbeddedSignup),
                ]),
            ]);
    }

    private function getPhoneNumberStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Phone Number')
            ->label('3. Phone Number')
            ->schema([
                Forms\Components\Select::make('search_country')
                    ->label('Country')
                    ->options([
                        'US' => 'United States',
                        'GB' => 'United Kingdom',
                        'CA' => 'Canada',
                        'IN' => 'India',
                        'AU' => 'Australia',
                    ])
                    ->default('US')
                    ->required()
                    ->disabled($this->numberPurchased),

                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('searchNumbers')
                        ->label('Search Numbers')
                        ->action('searchNumbers')
                        ->disabled($this->isSearching || $this->numberPurchased),
                ]),

                Forms\Components\Placeholder::make('numbers_list')
                    ->hidden(empty($this->availableNumbers))
                    ->content(new HtmlString(view('filament.admin.pages.whatsapp.partials.available-numbers', ['numbers' => $this->availableNumbers])->render())),
            ]);
    }

    private function getTemplatesStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Templates')
            ->label('4. Default Templates')
            ->schema([
                Forms\Components\Placeholder::make('templates_info')
                    ->content(new HtmlString('
                        <div class="mb-4">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">Setting up default message templates</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">We will create 8 utility templates for common gym communications. These will be submitted for Meta approval.</p>
                        </div>
                    ')),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('createTemplates')
                        ->label($this->templatesCreated ? 'Templates Created' : 'Create & Submit Templates')
                        ->color('primary')
                        ->action('createDefaultTemplates')
                        ->disabled($this->templatesCreated)
                        ->icon($this->templatesCreated ? 'heroicon-o-check-circle' : null),
                ]),
            ]);
    }

    private function getWelcomeCreditsStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Welcome Credits')
            ->label('5. Welcome Credits')
            ->schema([
                Forms\Components\Placeholder::make('welcome_info')
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
                        ->icon($this->welcomeCreditsClaimed ? 'heroicon-o-check-circle' : null),
                ]),
            ]);
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
        // $this->redirectRoute('filament.admin.pages.whats-app-setup');
    }

    public function retrySetup(): void
    {
        $this->loadWhatsAppData();
        $this->determineViewState();
        $this->redirectRoute('filament.admin.pages.whats-app-setup');
    }

    public function createSubaccount(): void
    {
        try {
            $gymId = $this->getGymId();
            $gym = $this->getGym();
            $user = Auth::user();

            $data = $this->form->getState();

            $result = app(TwilioAccountManager::class)->createSubaccount(
                $gymId,
                $data['business_name'] ?? $gym?->name ?? 'Unknown Gym',
                $data['business_email'] ?? $user?->email ?? 'admin@example.com'
            );

            if ($result['success']) {
                $this->whatsappAccount = WhatsAppAccount::where('gym_id', $gymId)->first();

                if ($this->whatsappAccount && isset($data['business_description'])) {
                    $this->whatsappAccount->update([
                        'business_description' => $data['business_description'],
                    ]);
                }

                $this->accountCreated = true;
                $this->setupStep = 2;

                Notification::make()
                    ->title('Account Created')
                    ->body('Your WhatsApp Business account has been created successfully.')
                    ->success()
                    ->send();

                $this->redirectRoute('filament.admin.pages.whats-app-setup');
            } else {
                Notification::make()
                    ->title('Failed to Create Account')
                    ->body($result['error'] ?? 'Unknown error')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Log::error('Failed to create subaccount', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
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
                    $this->wabaCreated = true;
                    $this->setupStep = 3;

                    Notification::make()
                        ->title('WABA Created')
                        ->body('Your WhatsApp Business Account has been created.')
                        ->success()
                        ->send();

                    $this->redirectRoute('filament.admin.pages.whats-app-setup');
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
        $this->wabaCreated = true;
        $this->setupStep = 3;

        Notification::make()
            ->title('Skipped')
            ->body('You can complete the WABA setup later.')
            ->warning()
            ->send();

        $this->redirectRoute('filament.admin.pages.whats-app-setup');
    }

    public function searchNumbers(): void
    {
        try {
            $data = $this->form->getState();
            $country = $data['search_country'] ?? 'US';

            $this->isSearching = true;

            $numbers = app(TwilioAccountManager::class)->searchNumbers($country, 'local', 5);

            $this->isSearching = false;

            if (empty($numbers)) {
                Notification::make()
                    ->title('No Numbers Found')
                    ->body('No available phone numbers in this country.')
                    ->warning()
                    ->send();
            } else {
                $this->availableNumbers = $numbers;

                Notification::make()
                    ->title('Numbers Found')
                    ->body('Found ' . count($numbers) . ' available numbers.')
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

            $result = app(TwilioAccountManager::class)->purchaseNumber(
                $this->getGymId(),
                $this->selectedNumber
            );

            if ($result['success']) {
                $this->phoneNumber = $this->whatsappAccount?->phoneNumber()->first();
                $this->availableNumbers = [];
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

    public function createDefaultTemplates(): void
    {
        try {
            app(WhatsAppTemplateService::class)->createDefaultTemplates($this->getGymId());

            $this->templatesCreated = true;
            $this->setupStep = 5;

            Notification::make()
                ->title('Templates Created')
                ->body('8 default templates have been created and submitted for approval.')
                ->success()
                ->send();

            // $this->redirectRoute('filament.admin.pages.whats-app-setup');
        } catch (\Exception $e) {
            Log::error('Failed to create default templates', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Template Creation Failed')
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
        $this->loadWhatsAppData();
        $this->determineViewState();
    }

    public function getTenant()
    {
        return Filament::getTenant();
    }

    public function getTenantSlug()
    {
        return $this->getTenant()->slug;
    }
}
