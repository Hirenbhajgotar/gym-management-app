<?php

namespace App\Filament\Admin\Pages;

use App\Enums\NotificationVerificationStatusEnum;
use App\Models\NotificationSetting;
use App\Services\TwilioAccountManager;
use App\Traits\HasTenantAccess;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString; // Added Cache facade
use libphonenumber\PhoneNumberType;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

/**
 * @property Form $form
 */
class WhatsAppSetup extends Page implements HasActions, HasForms
{
    use HasTenantAccess, InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'WhatsApp Setup';

    protected static ?string $navigationGroup = 'Settings';

    protected static string $view = 'filament.admin.pages.whats-app-setup';

    /** @var \App\Models\User */
    public $user;

    public ?array $data = [];

    public ?NotificationSetting $settings = null;

    public ?array $accountStatus = null;

    // 'loading', 'onboarding', 'create', 'verify', 'active', 'error'
    public string $viewState = 'loading';

    // Onboarding State Management
    public ?string $setupOption = null; // 'twilio', 'migrate'

    public int $twilioStep = 1; // 1: Search, 2: Confirm & Buy

    public array $availableNumbers = [];

    public ?string $selectedNumberForPurchase = null;

    public bool $isSearching = false;

    public function mount(): void
    {
        $this->loadSettings();
        $this->determineViewState();

        // Try to restore wizard state
        $this->restoreState();

        // If no restored data, initialize defaults
        if (empty($this->data)) {
            $this->initializeFormData();
        }
    }

    private function loadSettings(): void
    {
        $this->settings = NotificationSetting::where(
            'gym_id',
            $this->getGymId(),
        )->first();
    }

    private function determineViewState(): void
    {
        if (
            ! $this->settings ||
            ! $this->settings->twilio_account_sid ||
            ! $this->settings->whatsapp_business_number ||
            ! $this->settings->whatsapp_sender_sid
        ) {
            $this->viewState = 'onboarding';
        } elseif ($this->settings->verification_status === NotificationVerificationStatusEnum::PENDING) {
            $this->viewState = 'verify';
        } elseif ($this->settings->verification_status === NotificationVerificationStatusEnum::VERIFIED) {
            $this->viewState = 'active';
        } else {
            $this->viewState = 'error';
        }
    }

    private function initializeFormData(): void
    {
        $gym = $this->getGym();
        $user = Auth::user();

        $this->data = [
            'gym_name' => $this->settings?->gym_name ?? $gym?->name,
            'whatsapp_number' => $this->settings?->whatsapp_business_number ?? null,
            'owner_email' => $this->settings?->owner_email ?? $user?->email,
            'verification_code' => null,
            // Search defaults
            'search_country' => 'US',
            'search_type' => 'local',
        ];

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Option Selection (Step 0)
                Forms\Components\Section::make('Setup WhatsApp Business')
                    ->description('Choose how you want to set up your WhatsApp Business account.')
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('select_twilio')
                                ->label('Setup with New Number')
                                // ->description('Purchase a new number. We handle the technical details.')
                                ->icon('heroicon-o-plus-circle')
                                ->color(fn () => $this->setupOption === 'twilio' ? 'primary' : 'gray')
                                ->action(fn () => $this->selectOption('twilio')),

                            Forms\Components\Actions\Action::make('select_migrate')
                                ->label('Migrate Existing API')
                                // ->description('Use your existing WhatsApp Business API setup.')
                                ->icon('heroicon-o-arrow-path-rounded-square')
                                ->color(fn () => $this->setupOption === 'migrate' ? 'primary' : 'gray')
                                ->action(fn () => $this->selectOption('migrate')),
                        ])->alignCenter()->columnSpanFull(),

                        // Migrate Placeholder
                        Forms\Components\Placeholder::make('migrate_info')
                            ->hidden(fn () => $this->setupOption !== 'migrate')
                            ->content(new HtmlString('
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-4">
                                    <h3 class="text-lg font-medium text-yellow-800">Coming Soon</h3>
                                    <p class="text-yellow-700">The ability to migrate existing WhatsApp API accounts is currently under development. Please choose "Setup with New Number" or check back later.</p>
                                </div>
                            ')),

                        // Twilio Flow Container
                        Forms\Components\Group::make()
                            ->hidden(fn () => $this->setupOption !== 'twilio')
                            ->schema([
                                // Step 1: Search
                                Forms\Components\Fieldset::make('Find a Number')
                                    ->hidden(fn () => $this->twilioStep !== 1)
                                    ->schema([
                                        Forms\Components\Select::make('search_country')
                                            ->label('Country')
                                            ->options([
                                                'US' => 'United States',
                                                'GB' => 'United Kingdom',
                                                'CA' => 'Canada',
                                                'IN' => 'India', // Note: Twilio IN numbers are regulated
                                                'AU' => 'Australia',
                                            ])
                                            ->default('US')
                                            ->required()
                                            ->live(), // Trigger update hook

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('search_numbers')
                                                ->label('Find Available Number')
                                                ->action(fn () => $this->searchNumbers()),
                                        ]),
                                    ]),

                                // Step 2: Confirm
                                Forms\Components\Fieldset::make('Confirm Purchase')
                                    ->hidden(fn () => $this->twilioStep !== 2)
                                    ->schema([
                                        Forms\Components\Placeholder::make('confirm_details')
                                            ->content(fn () => new HtmlString("
                                                <div class='text-sm'>
                                                    <p class='mb-2'>You are about to purchase the phone number: <strong>{$this->selectedNumberForPurchase}</strong></p>
                                                    <p class='mb-2'>By proceeding, we will:</p>
                                                    <ul class='list-disc list-inside ml-4 mb-4 text-gray-600'>
                                                        <li>Purchase this number from Twilio</li>
                                                        <li>Create a whatsapp business account for your gym</li>
                                                        <li>Configure message services</li>
                                                    </ul>
                                                    <p class='text-xs text-gray-500'>* Standard Twilio rates apply for the platform.</p>
                                                </div>
                                            ")),

                                        Forms\Components\TextInput::make('gym_name')
                                            ->live(debounce: 500) // Trigger update hook
                                            ->label('Business Name')
                                            ->helperText('This name will be used for your WhatsApp profile.')
                                            ->required(),

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('back_step_search')
                                                ->label('Back')
                                                ->color('gray')
                                                ->action(fn () => $this->twilioStep = 1),

                                            Forms\Components\Actions\Action::make('purchase_confirm')
                                                ->label('Confirm & Setup')
                                                ->color('primary')
                                                ->requiresConfirmation()
                                                ->action(fn () => $this->purchaseAndSetup()),
                                        ])->alignRight(),
                                    ]),
                            ]),
                    ])
                    ->visible(fn () => $this->viewState === 'onboarding'),

                // Active Status Section (Existing)
                Forms\Components\Section::make('Account Status')
                    ->description('Your WhatsApp integration is active')
                    ->schema([
                        Forms\Components\Placeholder::make('active_info')
                            ->name('active_info_placeholder') // Added unique name
                            ->label('')
                            ->content(
                                new HtmlString('
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <p class="text-sm font-medium text-green-700">WhatsApp Business integration is active and ready to use!</p>
                                    </div>
                                </div>
                            '),
                            ),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Placeholder::make('status_display')
                                ->label('Status')
                                ->content(
                                    fn () => new HtmlString('
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            ✓ Verified
                                        </span>
                                    '),
                                ),

                            Forms\Components\Placeholder::make('number_display')
                                ->label('WhatsApp Number')
                                ->content(
                                    fn () => $this->settings
                                        ?->whatsapp_business_number ?? 'N/A',
                                ),

                            Forms\Components\Placeholder::make(
                                'account_created',
                            )
                                ->label('Account Created')
                                ->content(
                                    fn () => $this->settings?->created_at?->format(
                                        'M d, Y g:i A',
                                    ) ?? 'N/A',
                                ),

                            Forms\Components\Placeholder::make('verified_at')
                                ->label('Verified At')
                                ->content(
                                    fn () => $this->settings?->verified_at?->format(
                                        'M d, Y g:i A',
                                    ) ?? 'N/A',
                                ),
                        ]),
                    ])
                    ->visible(fn () => $this->viewState === 'active'),
            ])
            ->statePath('data');
    }

    public function selectOption(string $option): void
    {
        $this->setupOption = $option;
        $this->saveState();
    }

    public function searchNumbers(): void
    {
        $data = $this->form->getState();
        $country = $data['search_country'] ?? 'US';

        try {
            $manager = app(TwilioAccountManager::class);
            // Fetch only 1 number
            $numbers = $manager->searchAvailablePhoneNumbers($country, 'local', 1);

            if (empty($numbers)) {
                Notification::make()->title('No numbers found for this country.')->warning()->send();
            } else {
                // Auto-select the first number
                $this->availableNumbers = $numbers;
                $this->selectedNumberForPurchase = $numbers[0]['phoneNumber'];

                // Advance to next step
                $this->twilioStep = 2;

                Notification::make()->title('Number Found!')->body("Found available number: {$this->selectedNumberForPurchase}")->success()->send();

                $this->saveState();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Error searching numbers')->body($e->getMessage())->danger()->send();
        }
    }

    public function purchaseAndSetup(): void
    {
        $data = $this->form->getState();

        if (empty($this->selectedNumberForPurchase)) {
            Notification::make()->title('Please select a number first.')->warning()->send();

            return;
        }

        try {
            $manager = app(TwilioAccountManager::class);

            $result = $manager->setupAccountWithPurchasedNumber(
                tenantId: $this->getGymId(),
                gymOwnerName: Auth::user()->name,
                gymName: $data['gym_name'],
                phoneNumber: $this->selectedNumberForPurchase,
                email: Auth::user()->email
            );

            if ($result['success']) {
                $this->clearState(); // Setup complete, clear wizard cache
                Notification::make()->title('Setup Complete!')->body('Your number has been purchased and account setup.')->success()->send();
                $this->refresh();
            } else {
                Notification::make()->title('Setup Failed')->body($result['error'])->danger()->send();
            }

        } catch (\Exception $e) {
            Notification::make()->title('Setup Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function verifyNumberAction(): Action
    {
        return Action::make('verifyNumber')
            ->label('Verify Code')
            ->color('success')
            ->form([
                Forms\Components\TextInput::make('code')
                    ->label('Verification Code')
                    ->placeholder('123456')
                    ->required()
                    ->numeric(),
            ])
            ->action(fn (array $data) => $this->verifyCode($data));
    }

    public function resendCodeAction(): Action
    {
        return Action::make('resendCode')
            ->label('Resend Code')
            ->color('gray')
            ->action(fn () => $this->resendCode());
    }

    public function checkMessageAction(): Action
    {
        return Action::make('checkMessage')
            ->label('Check for Code')
            ->color('info')
            ->action(fn () => $this->checkIncomingMessage());
    }

    public function resetAction(): Action
    {
        return Action::make('reset')
            ->label('Reset & Start Over')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function () {
                // Optional: Clear settings or just state?
                // For now, just clear state to force re-evaluation or let user manage manually
                // If we want to truly reset, we might need to clear the specific fields in DB
                // But deleting settings is risky.
                // Let's just clear the verification status to allow retry if stuck
                if ($this->settings) {
                    $this->settings->update([
                        'verification_status' => null, // Clear status to force onboarding check
                        'whatsapp_business_number' => null, // Clear number to restart search
                        // keep sid/token as they are account level
                    ]);
                    $this->refresh();
                }
            });
    }

    public function verifyCode(array $data): void
    {
        try {
            $manager = app(TwilioAccountManager::class);
            $result = $manager->completeWhatsAppVerification($this->getGymId(), $data['code']);

            if ($result['success']) {
                Notification::make()->title('Verified!')->success()->send();
                $this->refresh();
            } else {
                Notification::make()->title('Verification Failed')->body($result['error'])->danger()->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function resendCode(): void
    {
        try {
            if (! $this->settings || ! $this->settings->twilio_account_sid) {
                throw new \Exception('Twilio account not set up.');
            }

            $manager = app(TwilioAccountManager::class);
            $result = $manager->startWhatsAppVerification(
                $this->settings->twilio_account_sid,
                $this->settings->twilio_auth_token,
                $this->settings->whatsapp_business_number
            );

            if ($result['success']) {
                Notification::make()->title('Code Resent')->success()->send();
            } else {
                Notification::make()->title('Failed to resend')->body($result['error'])->danger()->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function checkIncomingMessage(): void
    {
        try {
            if (! $this->settings || ! $this->settings->whatsapp_business_number) {
                return;
            }

            $manager = app(TwilioAccountManager::class);
            $messageBody = $manager->fetchLatestIncomingMessage($this->getGymId(), $this->settings->whatsapp_business_number);
            Log::info('Message Body: ' . $messageBody);

            if ($messageBody) {
                // Try to extract 6-digit code
                preg_match('/\b\d{6}\b/', $messageBody, $matches);
                $code = $matches[0] ?? null;

                if ($code) {
                    Notification::make()
                        ->title('Code Found: ' . $code)
                        ->body("Latest Message: {$messageBody}")
                        ->success()
                        ->persistent()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('fill_code')
                                ->button()
                                ->label('Fill Code')
                                ->action(function () use ($code) {
                                    // Dispatch event or just show it (auto-filling via action in valid component context is tricky)
                                    // For now, simpler to just show it clearly.
                                    // But since we are in a page component, we can pre-fill a property?
                                    // No, the verify action is a modal form.
                                    // So we just tell them the code.
                                    // Actually, we could verify it directly!
                                    $this->verifyCode(['code' => $code]);
                                }),
                        ])
                        ->send();
                } else {
                    Notification::make()
                        ->title('Message Received')
                        ->body($messageBody)
                        ->info()
                        ->send();
                }
            } else {
                Notification::make()->title('No messages yet')->body('Wait a moment and try again.')->warning()->send();
            }

        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function testMessageAction(): Action
    {
        return Action::make('testMessage')
            ->label('Send Test Message')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->size('xl')
            ->outlined()
            ->form([
                PhoneInput::make('test_phone_number')
                    ->defaultCountry('IN')
                    ->initialCountry('IN')
                    ->label('Recipient Phone Number')
                    ->required()
                    ->validateFor(
                        country: ['IN', 'PK', 'BD', 'US'],
                        type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE],
                        lenient: true,
                    )
                    ->placeholder('1234567890')
                    ->helperText('Enter the phone number to receive the test message, including country code.'),
            ])
            ->requiresConfirmation()
            ->modalHeading('Send Test Message')
            ->modalDescription(
                'This will send a test WhatsApp message to verify your integration is working.',
            )
            ->action(fn (array $data) => $this->sendTestMessage($data));
    }

    public function sendTestMessage(array $data = []): void
    {
        if (! $this->settings) {
            Notification::make()
                ->title('Error')
                ->body('No account settings found')
                ->danger()
                ->send();

            return;
        }

        $userId = Auth::id();
        $rateLimitKey = "whatsapp-test-message:{$userId}:{$this->settings->gym_id}";
        $dailyLimit = 3;

        // Check if user has reached the daily limit
        if (RateLimiter::tooManyAttempts($rateLimitKey, $dailyLimit)) {
            $availableIn = RateLimiter::availableIn($rateLimitKey);
            $hours = floor($availableIn / 3600);
            $minutes = floor(($availableIn % 3600) / 60);

            Notification::make()
                ->title('⚠️ Daily Limit Reached')
                ->body(
                    "You have reached the daily limit of {$dailyLimit} test messages. Please try again in {$hours}h {$minutes}m.",
                )
                ->warning()
                ->duration(7000)
                ->send();

            return;
        }

        try {
            $accountManager = app(TwilioAccountManager::class);
            $testNumber = $data['test_phone_number'] ?? null;
            $result = $accountManager->sendTestWhatsAppMessage($this->settings, $testNumber);

            if ($result['success']) {
                // Only increment rate limiter on successful sends
                RateLimiter::hit($rateLimitKey, 86400); // 24 hours = 86400 seconds

                Notification::make()
                    ->title('✅ Test Message Sent!')
                    ->body(
                        'A test message has been sent to your WhatsApp number. Please check your WhatsApp to confirm receipt.',
                    )
                    ->success()
                    ->duration(6000)
                    ->send();

            } else {
                throw new \Exception($result['error'] ?? 'Failed to send test message. Please try again or contact support.');
            }
        } catch (\Exception $e) {
            // Report the error using ErrorReportService
            $errorReport = resolve(\App\Services\ErrorReportService::class)->reportException(
                exception: $e,
                userDescription: 'WhatsApp Test Message Failed from WhatsApp Setup Page',
                reportType: 'whatsapp_integration_error'
            );

            Notification::make()
                ->title('❌ Test Message Failed')
                ->body(
                    'An error occurred while sending the test message. The error has been logged for review.'
                )
                ->danger()
                ->duration(7000)
                ->send();
        }
    }

    public function refresh(): void
    {
        $this->loadSettings();
        $this->determineViewState();
        $this->initializeFormData();
    }

    public function updated(string $propertyName): void
    {
        $this->saveState();
    }

    protected function saveState(): void
    {
        $key = 'whatsapp_setup_state:' . Auth::id() . ':' . $this->getGymId();

        Cache::put($key, [
            'data' => $this->data,
            'setupOption' => $this->setupOption,
            'twilioStep' => $this->twilioStep,
            'availableNumbers' => $this->availableNumbers,
            'selectedNumberForPurchase' => $this->selectedNumberForPurchase,
        ], 21600); // 6 hours
    }

    protected function restoreState(): void
    {
        $key = 'whatsapp_setup_state:' . Auth::id() . ':' . $this->getGymId();

        if (Cache::has($key)) {
            $state = Cache::get($key);

            $this->data = $state['data'] ?? [];
            $this->setupOption = $state['setupOption'] ?? null;
            $this->twilioStep = $state['twilioStep'] ?? 1;
            $this->availableNumbers = $state['availableNumbers'] ?? [];
            $this->selectedNumberForPurchase = $state['selectedNumberForPurchase'] ?? null;

            // Re-hydrate form
            $this->form->fill($this->data);
        }
    }

    protected function clearState(): void
    {
        $key = 'whatsapp_setup_state:' . Auth::id() . ':' . $this->getGymId();
        Cache::forget($key);
    }
}
