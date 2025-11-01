<?php

namespace App\Filament\Admin\Pages;

use App\Enums\NotificationVerificationStatusEnum;
use App\Models\NotificationSetting;
use App\Services\TwilioAccountManager;
use Filament\Actions\Action;
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
use Illuminate\Support\HtmlString;

class WhatsAppSetup extends Page implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'WhatsApp Setup';

    protected static ?string $navigationGroup = 'Settings';

    protected static string $view = 'filament.admin.pages.whats-app-setup';

    /** @var \App\Models\User */
    public $user;

    public ?array $data = [];

    public ?NotificationSetting $settings = null;

    public ?array $accountStatus = null;

    public string $viewState = 'loading';

    public function mount(): void
    {
        // Pre-fill the form with sensible defaults from the current tenant (gym) and user
        $this->loadSettings();
        $this->determineViewState();
        $this->initializeFormData();
    }

    private function loadSettings(): void
    {
        $this->settings = NotificationSetting::where('gym_id', Filament::getTenant()?->id)->first();
    }

    private function determineViewState(): void
    {
        if (! $this->settings || ! $this->settings->twilio_account_sid) {
            $this->viewState = 'create';
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
        $gym = Filament::getTenant();
        $user = Auth::user();

        $this->data = [
            'gym_name' => $this->settings?->gym_name ?? $gym?->name,
            'whatsapp_number' => $this->settings?->whatsapp_business_number ?? null,
            'owner_email' => $this->settings?->owner_email ?? $user?->email,
            'verification_code' => null,
        ];

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Create Account Section
                Forms\Components\Section::make('WhatsApp Business Setup')
                    ->description('Create your automated WhatsApp Business account')
                    ->schema([
                        Forms\Components\Placeholder::make('create_info')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="bg-blue-50 border border-blue-200 dark:bg-gray-800 dark:border-gray-800 rounded-lg p-4">
                                    <p class="text-sm text-blue-700">We will handle all technical setup for you. Just provide your WhatsApp Business number and we will create your account automatically.</p>
                                </div>
                            ')),

                        Forms\Components\TextInput::make('gym_name')
                            ->label('Gym Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('WhatsApp Business Number')
                            ->required()
                            ->tel()
                            ->placeholder('+1234567890')
                            ->helperText('Include country code (e.g., +1 for US)')
                            ->rules(['regex:/^\+[1-9]\d{1,14}$/']),

                        Forms\Components\TextInput::make('owner_email')
                            ->label('Owner Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Placeholder::make('setup_steps')
                            ->label('What happens next')
                            ->content(new HtmlString('
                                <div class="text-sm text-gray-600 space-y-2">
                                    <div class="flex items-start gap-2">
                                        <span class="flex-shrink-0 w-5 h-5 bg-primary-500 text-white rounded-full flex items-center justify-center text-xs">1</span>
                                        <span>Create dedicated Twilio account</span>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="flex-shrink-0 w-5 h-5 bg-primary-500 text-white rounded-full flex items-center justify-center text-xs">2</span>
                                        <span>Configure WhatsApp number</span>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="flex-shrink-0 w-5 h-5 bg-primary-500 text-white rounded-full flex items-center justify-center text-xs">3</span>
                                        <span>Send verification code to WhatsApp</span>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="flex-shrink-0 w-5 h-5 bg-primary-500 text-white rounded-full flex items-center justify-center text-xs">4</span>
                                        <span>Verify and start sending notifications</span>
                                    </div>
                                </div>
                            ')),
                    ])
                    ->visible(fn () => $this->viewState === 'create'),

                // Verification Section
                Forms\Components\Section::make('Verify Your Number')
                    ->description('Enter the verification code sent to your WhatsApp')
                    ->schema([
                        Forms\Components\Placeholder::make('verify_info')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                                    <p class="text-sm text-orange-700">A verification code has been sent to your WhatsApp Business number. Please check your messages and enter the code below.</p>
                                </div>
                            ')),

                        Forms\Components\TextInput::make('verification_code')
                            ->label('Verification Code')
                            ->required()
                            ->numeric()
                            ->length(6)
                            ->placeholder('000000')
                            ->helperText('Enter the 6-digit code from WhatsApp'),

                        Forms\Components\Placeholder::make('resend_info')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="text-sm text-gray-600">
                                    <p>Didn\'t receive the code? Click the "Resend Code" button below.</p>
                                </div>
                            ')),
                    ])
                    ->visible(fn () => $this->viewState === 'verify'),

                // Active Status Section
                Forms\Components\Section::make('Account Status')
                    ->description('Your WhatsApp integration is active')
                    ->schema([
                        Forms\Components\Placeholder::make('active_info')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <p class="text-sm font-medium text-green-700">WhatsApp Business integration is active and ready to use!</p>
                                    </div>
                                </div>
                            ')),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Placeholder::make('status_display')
                                    ->label('Status')
                                    ->content(fn () => new HtmlString('
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            ✓ Verified
                                        </span>
                                    ')),

                                Forms\Components\Placeholder::make('number_display')
                                    ->label('WhatsApp Number')
                                    ->content(fn () => $this->settings?->whatsapp_business_number ?? 'N/A'),

                                Forms\Components\Placeholder::make('account_created')
                                    ->label('Account Created')
                                    ->content(fn () => $this->settings?->created_at?->format('M d, Y g:i A') ?? 'N/A'),

                                Forms\Components\Placeholder::make('verified_at')
                                    ->label('Verified At')
                                    ->content(fn () => $this->settings?->verified_at?->format('M d, Y g:i A') ?? 'N/A'),
                            ]),
                    ])
                    ->visible(fn () => $this->viewState === 'active'),
            ])
            ->statePath('data');
    }

    // private function getActionValue(): string|null
    // {
    //     if (! $this->hasExistingAccount()) {
    //         return 'create_account';
    //     } elseif ($this->needsVerification()) {
    //         return 'verify';
    //     } elseif ($this->hasExistingAccount()) {
    //         return 'test_message';
    //     } else {
    //         return null;
    //     }
    // }

    public function createAccountAction(): Action
    {
        return Action::make('createAccount')
            ->label('Create WhatsApp Account')
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->size('xl')
            ->requiresConfirmation()
            ->modalHeading('Create WhatsApp Account')
            ->modalDescription('This will create a new Twilio account and send a verification code to your WhatsApp number.')
            ->action(fn () => $this->createTwilioAccount());
    }

    public function verifyNumberAction(): Action
    {
        return Action::make('verifyNumber')
            ->label('Verify Number')
            ->icon('heroicon-o-check-circle')
            ->color('primary')
            ->size('xl')
            ->action(fn () => $this->verifyWhatsAppNumber());
    }

    public function resendCodeAction(): Action
    {
        return Action::make('resendCode')
            ->label('Resend Code')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->size('xl')
            ->outlined()
            ->action(fn () => $this->resendVerificationCode());
    }

    public function testMessageAction(): Action
    {
        return Action::make('testMessage')
            ->label('Send Test Message')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->size('xl')
            ->outlined()
            ->requiresConfirmation()
            ->modalHeading('Send Test Message')
            ->modalDescription('This will send a test WhatsApp message to verify your integration is working.')
            ->action(fn () => $this->sendTestMessage());
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $action = match ($data['action_value'] ?? null) {
            'create_account' => 'createTwilioAccount',
            'verify' => 'verifyWhatsAppNumber',
            'test_message' => 'sendTestMessage',
            default => null,
        };

        if ($action) {
            $this->$action();
        } else {
            Notification::make()
                ->title('No action to perform')
                ->body('Please select a valid action.')
                ->warning()
                ->send();
        }
    }

    public function createTwilioAccount(): void
    {
        $data = $this->form->getState();
        $accountManager = app(TwilioAccountManager::class);

        $result = $accountManager->createAccountForGymOwner(
            Filament::getTenant()?->id,
            Auth::user()->name,
            $data['gym_name'],
            $data['whatsapp_number'],
            $data['owner_email']
        );

        if ($result['success']) {
            Notification::make()
                ->title('Account Created Successfully!')
                ->body('Please check your WhatsApp for the verification code.')
                ->success()
                ->duration(5000)
                ->send();

            $this->refresh();
        } else {
            Notification::make()
                ->title('Account Creation Failed')
                ->body($result['message'] ?? $result['error'] ?? 'An error occurred')
                ->danger()
                ->duration(8000)
                ->send();
        }
    }

    public function verifyWhatsAppNumber(): void
    {
        $data = $this->form->getState();

        if (empty($data['verification_code'])) {
            Notification::make()
                ->title('Verification Code Required')
                ->body('Please enter the verification code.')
                ->warning()
                ->send();

            return;
        }

        $accountManager = app(TwilioAccountManager::class);

        $result = $accountManager->completeWhatsAppVerification(
            Filament::getTenant()?->id,
            $data['verification_code']
        );

        if ($result['success']) {
            Notification::make()
                ->title('Verification Successful!')
                ->body('Your WhatsApp integration is now active. You can start sending notifications.')
                ->success()
                ->duration(5000)
                ->send();

            $this->refresh();
        } else {
            Notification::make()
                ->title('Verification Failed')
                ->body($result['error'] ?? 'Invalid verification code')
                ->danger()
                ->duration(5000)
                ->send();
        }
    }

    public function resendVerificationCode(): void
    {
        if (! $this->settings) {
            Notification::make()
                ->title('Error')
                ->body('No account settings found')
                ->danger()
                ->send();

            return;
        }

        $accountManager = app(TwilioAccountManager::class);

        $result = $accountManager->startWhatsAppVerification(
            $this->settings->twilio_account_sid,
            $this->settings->twilio_auth_token,
            $this->settings->whatsapp_business_number
        );

        if ($result['success']) {
            Notification::make()
                ->title('Verification Code Resent')
                ->body('A new verification code has been sent to your WhatsApp.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Failed to Resend Code')
                ->body($result['error'] ?? 'An error occurred')
                ->danger()
                ->send();
        }
    }

    public function sendTestMessage(): void
    {
        if (! $this->settings) {
            Notification::make()
                ->title('Error')
                ->body('No account settings found')
                ->danger()
                ->send();

            return;
        }

        $accountManager = app(TwilioAccountManager::class);
        $result = $accountManager->sendTestWhatsAppMessage($this->settings);

        if ($result['success']) {
            Notification::make()
                ->title('Test Message Sent!')
                ->body('Check your WhatsApp for the test message.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Test Message Failed')
                ->body($result['error'] ?? 'Failed to send test message')
                ->danger()
                ->send();
        }
    }

    public function refresh(): void
    {
        $this->loadSettings();
        $this->determineViewState();
        $this->initializeFormData();
    }

    // public function refreshComponent(): void
    // {
    //     $this->loadAccountStatus();
    //     // parent::refresh();
    // }

    private function loadAccountStatus(): void
    {
        $accountManager = app(TwilioAccountManager::class);
        $this->accountStatus = $accountManager->getAccountStatus(Filament::getTenant()?->id);

        if (! $this->hasExistingAccount()) {
            $this->viewState = 'create';
        } elseif ($this->needsVerification()) {
            $this->viewState = 'verify';
        } else {
            $this->viewState = 'status';
        }
    }

    public function hasExistingAccount(): bool
    {
        return $this->accountStatus && $this->accountStatus['status'] !== 'not_created';
    }

    public function needsVerification(): bool
    {
        return $this->accountStatus &&
            isset($this->accountStatus['verification_status']) &&
            $this->accountStatus['verification_status'] === NotificationVerificationStatusEnum::PENDING;
    }

    // private function getAccountStatusDisplay(): string
    // {
    //     if (! $this->accountStatus) {
    //         return 'Loading...';
    //     }

    //     $status = $this->accountStatus;

    //     return "<div class='space-y-2'>
    //         <div><strong>Status:</strong> <span class='text-green-600'>" . ucfirst($status['status']) . "</span></div>
    //         <div><strong>Verification:</strong> <span class='" .
    //         (isset($status['verification_status']) && $status['verification_status'] === NotificationVerificationStatusEnum::VERIFIED ? 'text-green-600' : 'text-orange-600') . "'>" .
    //         ucfirst($status['verification_status']->value ?? 'unknown') . '</span></div>' .
    //         (isset($status['balance']) ? '<div><strong>Account Balance:</strong> $' . $status['balance'] . '</div>' : '') .
    //         (isset($status['messages_sent']) ? '<div><strong>Messages This Month:</strong> ' . $status['messages_sent'] . '</div>' : '') .
    //         '</div>';
    // }
}
