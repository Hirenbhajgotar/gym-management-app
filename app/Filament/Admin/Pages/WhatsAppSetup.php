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

    public int $totalSteps = 9;

    public array $availableNumbers = [];

    public ?string $selectedNumber = null;

    public bool $isSearching = false;

    public array $businessProfile = [];

    public array $defaultTemplatesStatus = [];

    public bool $welcomeCreditsClaimed = false;

    public bool $useIndividualWaba = false;

    public bool $requiresEmbeddedSignup = false;

    public ?string $embeddedSignupUrl = null;

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
            if ($this->useIndividualWaba && ! $this->whatsappAccount->isEmbeddedSignupComplete()) {
                return 3;
            }

            return 3;
        }

        if (! $this->phoneNumber) {
            return 4;
        }

        if (! $this->whatsappAccount->messaging_service_sid) {
            return 6;
        }

        $defaultTemplates = WhatsAppTemplate::where('gym_id', $this->getGymId())
            ->where('is_default', true)
            ->count();

        if ($defaultTemplates === 0) {
            return 7;
        }

        if (! $this->welcomeCreditsClaimed) {
            return 9;
        }

        return 10;
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
            Forms\Components\Section::make('Welcome to WhatsApp Business')
                ->description('Set up your dedicated WhatsApp Business account to communicate with your members.')
                ->schema([
                    Forms\Components\Placeholder::make('intro')
                        ->content(new HtmlString('
                            <div class="text-center py-8">
                                <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Get Started with WhatsApp Business</h3>
                                <p class="text-gray-500 mb-6">We\'ll create a dedicated WhatsApp Business account for your gym. Each gym gets its own isolated account for complete privacy and control.</p>
                            </div>
                        ')),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('start_setup')
                            ->label('Start Setup')
                            ->color('primary')
                            ->icon('heroicon-o-rocket-launch')
                            ->action(fn () => $this->startSetup()),
                    ])->alignCenter(),
                ]),
        ];
    }

    private function getSetupSchema(): array
    {
        return [
            Forms\Components\Wizard::make('setupWizard')
                ->steps([
                    Forms\Components\Wizard\Step::make('Create Account')
                        ->label('1. Create Account')
                        ->schema([
                            Forms\Components\Placeholder::make('step1_info')
                                ->content(new HtmlString('
                                    <div class="mb-4">
                                        <h3 class="font-medium">Creating your dedicated WhatsApp Business account...</h3>
                                        <p class="text-sm text-gray-500">This will create a subaccount under our master Twilio account.</p>
                                    </div>
                                ')),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('createAccount')
                                    ->label('Create Subaccount')
                                    ->color('primary')
                                    ->action(fn () => $this->createSubaccount())
                                    ->disabled($this->whatsappAccount !== null),
                            ]),
                        ]),

                    Forms\Components\Wizard\Step::make('Business Profile')
                        ->label('2. Business Profile')
                        ->schema([
                            Forms\Components\TextInput::make('business_name')
                                ->label('Business Name')
                                ->required()
                                ->default($this->getGym()?->name),

                            Forms\Components\TextInput::make('business_email')
                                ->label('Business Email')
                                ->email()
                                ->required()
                                ->default(Auth::user()?->email),

                            Forms\Components\Textarea::make('business_description')
                                ->label('Business Description')
                                ->rows(3)
                                ->placeholder('Describe your gym business...'),

                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('saveProfile')
                                    ->label('Save & Continue')
                                    ->color('primary')
                                    ->action(fn () => $this->saveBusinessProfile()),
                            ]),
                        ]),

                    Forms\Components\Wizard\Step::make('WABA Setup')
                        ->label('3. WhatsApp Business')
                        ->schema([
                            Forms\Components\Placeholder::make('waba_info')
                                ->content(new HtmlString('
                                    <div class="mb-4">
                                        <h3 class="font-medium">Creating WhatsApp Business Account (WABA)</h3>
                                        <p class="text-sm text-gray-500">This is required to send WhatsApp messages to your members.</p>
                                    </div>
                                ')),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('createWaba')
                                    ->label($this->useIndividualWaba ? 'Create Individual WABA' : 'Create Shared WABA')
                                    ->color('primary')
                                    ->action(fn () => $this->createWaba())
                                    ->disabled($this->whatsappAccount?->waba_id !== null),
                            ]),
                            Forms\Components\Placeholder::make('embedded_signup_info')
                                ->hidden(! $this->requiresEmbeddedSignup)
                                ->content(new HtmlString('
                                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <h4 class="font-medium text-blue-800 mb-2">Complete WABA Setup</h4>
                                        <p class="text-sm text-blue-700 mb-4">Click the button below to connect your WhatsApp Business Account through Facebook.</p>
                                    </div>
                                ')),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('startEmbeddedSignup')
                                    ->label('Connect via Facebook')
                                    ->color('success')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(fn () => $this->startEmbeddedSignup())
                                    ->hidden(! $this->requiresEmbeddedSignup),
                                Forms\Components\Actions\Action::make('skipEmbeddedSignup')
                                    ->label('Skip for Now')
                                    ->color('gray')
                                    ->action(fn () => $this->skipEmbeddedSignup())
                                    ->hidden(! $this->requiresEmbeddedSignup),
                            ]),
                        ]),

                    Forms\Components\Wizard\Step::make('Phone Number')
                        ->label('4. Phone Number')
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
                                ->required(),

                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('searchNumbers')
                                    ->label('Search Numbers')
                                    ->action(fn () => $this->searchNumbers())
                                    ->disabled($this->isSearching),
                            ]),

                            Forms\Components\Placeholder::make('numbers_list')
                                ->hidden(fn () => empty($this->availableNumbers))
                                ->content(new HtmlString('
                                    <div class="mt-4">
                                        <h4 class="font-medium mb-2">Available Numbers</h4>
                                        <div class="space-y-2">
                                            @foreach($availableNumbers as $number)
                                                <div class="flex items-center justify-between p-3 border rounded-lg">
                                                    <div>
                                                        <span class="font-medium">{{ $number["phoneNumber"] }}</span>
                                                        <span class="text-sm text-gray-500 ml-2">{{ $number["locality"] ?? "" }}</span>
                                                    </div>
                                                    <button wire:click="selectNumber(\'{{ $number["phoneNumber"] }}\')" class="text-primary-600 hover:text-primary-700">
                                                        Select
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                ')),
                        ]),

                    Forms\Components\Wizard\Step::make('Templates')
                        ->label('5. Default Templates')
                        ->schema([
                            Forms\Components\Placeholder::make('templates_info')
                                ->content(new HtmlString('
                                    <div class="mb-4">
                                        <h3 class="font-medium">Setting up default message templates</h3>
                                        <p class="text-sm text-gray-500">We\'ll create 8 utility templates for common gym communications. These will be submitted for Meta approval.</p>
                                    </div>
                                ')),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('createTemplates')
                                    ->label('Create & Submit Templates')
                                    ->color('primary')
                                    ->action(fn () => $this->createDefaultTemplates()),
                            ]),
                        ]),

                    Forms\Components\Wizard\Step::make('Welcome Credits')
                        ->label('6. Welcome Credits')
                        ->schema([
                            Forms\Components\Placeholder::make('welcome_info')
                                ->content(new HtmlString('
                                    <div class="text-center py-6">
                                        <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                                            <span class="text-2xl">🎁</span>
                                        </div>
                                        <h3 class="font-medium text-lg mb-2">50 Free Welcome Credits!</h3>
                                        <p class="text-gray-500">Claim your 50 free WhatsApp message credits to get started.</p>
                                    </div>
                                ')),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('claimWelcome')
                                    ->label('Claim Welcome Credits')
                                    ->color('success')
                                    ->action(fn () => $this->claimWelcomeCredits())
                                    ->disabled($this->welcomeCreditsClaimed),
                            ]),
                        ]),
                ])
                ->skippable(false),
        ];
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
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
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
                            ->content(new HtmlString('
                                <div class="text-sm">
                                    <span class="font-medium">{{ $creditBalance?->remaining_credits ?? 0 }}</span>
                                    <span class="text-gray-500"> remaining</span>
                                </div>
                            ')),
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
                            ->action(fn (array $data) => $this->sendTestMessage($data)),

                        Forms\Components\Actions\Action::make('viewTemplates')
                            ->label('Manage Templates')
                            ->url(route('filament.admin.resources.whatsapp-templates.index')),
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
                                <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Setup Incomplete</h3>
                                <p class="text-gray-500 mb-4">Please contact support or try setting up again.</p>
                            </div>
                        ')),
                ]),
        ];
    }

    public function startSetup(): void
    {
        $this->viewState = 'setup';
        $this->setupStep = 1;
    }

    public function createSubaccount(): void
    {
        try {
            $gymId = $this->getGymId();
            $gym = $this->getGym();
            $user = Auth::user();

            $result = app(TwilioAccountManager::class)->createSubaccount(
                $gymId,
                $gym?->name ?? 'Unknown Gym',
                $user?->email ?? 'admin@example.com'
            );

            if ($result['success']) {
                $this->whatsappAccount = WhatsAppAccount::where('gym_id', $gymId)->first();
                $this->setupStep = 2;

                Notification::make()
                    ->title('Account Created')
                    ->body('Your WhatsApp Business subaccount has been created.')
                    ->success()
                    ->send();
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
                $this->whatsappAccount->refresh();

                if ($result['requires_embedded_signup'] ?? false) {
                    $this->requiresEmbeddedSignup = true;
                    $this->embeddedSignupUrl = app(EmbeddedSignupService::class)->getLoginUrl($gymId);

                    Notification::make()
                        ->title('WABA Setup Initiated')
                        ->body('Please complete the Facebook login to connect your WhatsApp Business Account.')
                        ->info()
                        ->send();
                } else {
                    $this->setupStep = 4;

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
        $this->setupStep = 4;

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

            $numbers = app(TwilioAccountManager::class)->searchNumbers($country, 'local', 5);

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
                $this->setupStep = 5;

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

    public function createDefaultTemplates(): void
    {
        try {
            app(WhatsAppTemplateService::class)->createDefaultTemplates($this->getGymId());

            $this->setupStep = 6;

            Notification::make()
                ->title('Templates Created')
                ->body('8 default templates have been created and submitted for approval.')
                ->success()
                ->send();
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

            $result = app(TwilioWhatsAppService::class)->sendTemplateMessage(
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
}
