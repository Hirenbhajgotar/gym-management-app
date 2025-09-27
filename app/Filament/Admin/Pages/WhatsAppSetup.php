<?php

namespace App\Filament\Admin\Pages;

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
use Illuminate\Support\HtmlString;

class WhatsAppSetup extends Page implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'WhatsApp Setup';

    protected static ?string $navigationGroup = 'Settings';

    protected static string $view = 'filament.admin.pages.whats-app-setup';

    public ?array $data = [];

    public ?array $accountStatus = null;

    public function mount(): void
    {
        // Pre-fill the form with sensible defaults from the current tenant (gym) and user
        $gym = Filament::getTenant();
        $user = auth()->user();

        $this->data = array_merge(
            $this->data ?? [],
            [
                'gym_name' => $gym?->name ?? null,
                'owner_email' => $user?->email ?? null,
            ]
        );

        // Ensure the Filament form has the state populated
        $this->form->fill($this->data);

        $this->loadAccountStatus();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('WhatsApp Business Setup')
                    ->description('We will create and manage your Twilio WhatsApp account automatically')
                    ->schema([
                        Forms\Components\Hidden::make('action_value')
                            ->default(function () {
                                if (! $this->hasExistingAccount()) {
                                    return 'create_account';
                                } elseif ($this->needsVerification()) {
                                    return 'verify';
                                } elseif ($this->hasExistingAccount()) {
                                    return 'test_message';
                                } else {
                                    return null;
                                }
                            }),
                        Forms\Components\Placeholder::make('info')
                            ->label('Automated Setup Process')
                            ->content('We will handle all technical setup for you. Just provide your WhatsApp Business number and we will do the rest!')
                            ->extraAttributes(['class' => 'text-info-600 bg-info-50 border border-info-200 rounded-lg p-4'])
                            ->visible(fn () => ! $this->hasExistingAccount()),

                        Forms\Components\TextInput::make('gym_name')
                            ->label('Gym Name')
                            ->required()
                            ->default(Filament::getTenant()?->name),

                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('WhatsApp Business Number')
                            ->required()
                            ->placeholder('+1234567890')
                            ->helperText('This should be your verified WhatsApp Business number with country code'),

                        Forms\Components\TextInput::make('owner_email')
                            ->label('Owner Email')
                            ->email()
                            ->required()
                            ->default(auth()->user()->email),

                        Forms\Components\Placeholder::make('setup_info')
                            ->content(new HtmlString('
                                <div class="text-sm text-gray-600">
                                    <h4 class="font-medium mb-2">What happens when you click "Create Account":</h4>
                                    <ol class="list-decimal list-inside space-y-1">
                                        <li>We create a dedicated Twilio account for your gym</li>
                                        <li>Your WhatsApp number gets configured automatically</li>
                                        <li>We send you a verification message</li>
                                        <li>Once verified, you can start sending notifications</li>
                                        <li>All costs are handled through your subscription with us</li>
                                    </ol>
                                </div>
                            ')),
                    ])
                    ->visible(fn () => ! $this->hasExistingAccount() || $this->needsVerification()),

                Forms\Components\Section::make('Account Status')
                    ->schema([
                        Forms\Components\Placeholder::make('account_info')
                            ->content(fn () => new HtmlString($this->getAccountStatusDisplay())),
                    ])
                    ->visible(fn () => $this->hasExistingAccount() && ! $this->needsVerification()),

                Forms\Components\Section::make('Verification')
                    ->schema([
                        Forms\Components\TextInput::make('verification_code')
                            ->label('Verification Code')
                            ->helperText('Enter the code sent to your WhatsApp number'),
                    ])
                    ->visible(fn () => $this->needsVerification()),
            ])
            ->statePath('data');
    }

    public function createAccountAction(): Action
    {
        return Action::make('create_account')
            ->label('Create WhatsApp Account')
            ->icon('heroicon-o-plus')
            ->color('success')
            ->action('createTwilioAccount');
    }

    public function verifyNumberAction(): Action
    {
        return Action::make('verify_number')
            ->label('Verify Number')
            ->icon('heroicon-o-check')
            ->color('primary')
            ->action('verifyWhatsAppNumber');
    }

    public function testMessageAction(): Action
    {
        return Action::make('test_message')
            ->label('Send Test Message')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->action('sendTestMessage');
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
            auth()->user()?->name,
            $data['gym_name'],
            $data['whatsapp_number'],
            $data['owner_email']
        );

        if ($result['success']) {
            Notification::make()
                ->title('Account Created Successfully!')
                ->body('Your Twilio account has been created. Please check your WhatsApp for a verification message.')
                ->success()
                ->send();

            $this->loadAccountStatus();
        } else {
            Notification::make()
                ->title('Account Creation Failed')
                ->body($result['message'] ?? $result['error'])
                ->danger()
                ->send();
        }
    }

    public function verifyWhatsAppNumber(): void
    {
        $data = $this->form->getState();

        $accountManager = app(TwilioAccountManager::class);

        $result = $accountManager->completeWhatsAppVerification(
            Filament::getTenant()?->id,
            $data['verification_code']
        );

        if ($result['success']) {
            Notification::make()
                ->title('Verification Successful!')
                ->body('Your WhatsApp integration is now active. You can start sending notifications to your members.')
                ->success()
                ->send();

            $this->loadAccountStatus();
        } else {
            Notification::make()
                ->title('Verification Failed')
                ->body($result['error'] ?? 'Invalid verification code')
                ->danger()
                ->send();
        }
    }

    public function sendTestMessage(): void
    {
        $accountManager = app(TwilioAccountManager::class);
        $settings = NotificationSetting::where('gym_id', Filament::getTenant()?->id)->first();

        $result = $accountManager->sendTestWhatsAppMessage($settings);

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

    private function loadAccountStatus(): void
    {
        $accountManager = app(TwilioAccountManager::class);
        $this->accountStatus = $accountManager->getAccountStatus(Filament::getTenant()?->id);
    }

    private function hasExistingAccount(): bool
    {
        return $this->accountStatus && $this->accountStatus['status'] !== 'not_created';
    }

    private function needsVerification(): bool
    {
        return $this->accountStatus &&
            isset($this->accountStatus['verification_status']) &&
            $this->accountStatus['verification_status'] === 'pending';
    }

    private function getAccountStatusDisplay(): string
    {
        if (! $this->accountStatus) {
            return 'Loading...';
        }

        $status = $this->accountStatus;

        return "<div class='space-y-2'>
            <div><strong>Status:</strong> <span class='text-green-600'>" . ucfirst($status['status']) . "</span></div>
            <div><strong>Verification:</strong> <span class='" .
            (isset($status['verification_status']) && $status['verification_status'] === 'verified' ? 'text-green-600' : 'text-orange-600') . "'>" .
            ucfirst($status['verification_status'] ?? 'unknown') . '</span></div>' .
            (isset($status['balance']) ? '<div><strong>Account Balance:</strong> $' . $status['balance'] . '</div>' : '') .
            (isset($status['messages_sent']) ? '<div><strong>Messages This Month:</strong> ' . $status['messages_sent'] . '</div>' : '') .
            '</div>';
    }
}
