<?php

namespace App\Filament\Admin\Pages\WhatsApp;

use App\Models\WhatsAppAccount;
use App\Services\TwilioAccountManager;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class ManualWabaConnection extends Page implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Manual WABA Connection';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static string $view = 'filament.admin.pages.whatsapp.manual-waba-connection';

    public ?WhatsAppAccount $whatsappAccount = null;

    public ?array $data = [];

    public function mount(): void
    {
        $gymId = Filament::getTenant()?->id;

        if ($gymId) {
            $this->whatsappAccount = WhatsAppAccount::where('gym_id', $gymId)->first();

            $this->form->fill([
                'waba_id' => $this->whatsappAccount?->waba_id,
                'phone_number' => $this->whatsappAccount?->phoneNumber()->first()?->phone_number,
                'meta_access_token' => '',
            ]);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Manual WABA Connection')
                    ->description('Connect your existing WhatsApp Business Account manually. Use this if Embedded Signup is not available or fails.')
                    ->schema([
                        Forms\Components\Placeholder::make('info_alert')
                            ->hiddenLabel()
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="flex p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400" role="alert">
                                    <svg class="flex-shrink-0 inline w-4 h-4 me-3 mt-[2px]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                                    </svg>
                                    <span class="sr-only">Info</span>
                                    <div>
                                        <span class="font-medium">Important:</span> This is a fallback option. For the best experience, use the Embedded Signup flow.
                                    </div>
                                </div>
                            '))
                            ->visible(config('services.twilio.embedded_signup_enabled', false)),

                        Forms\Components\TextInput::make('waba_id')
                            ->label('WABA ID')
                            ->helperText('Your WhatsApp Business Account ID from Meta Business Manager')
                            ->required()
                            ->placeholder('123456789012345'),

                        Forms\Components\TextInput::make('phone_number')
                            ->label('WhatsApp Phone Number')
                            ->tel()
                            ->helperText('The phone number associated with your WABA (e.g., +1234567890)')
                            ->required()
                            ->placeholder('+1234567890'),

                        Forms\Components\TextInput::make('meta_access_token')
                            ->label('Meta Access Token (Optional)')
                            ->helperText('Permanent access token from Meta Business Manager. Required for template management.')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state)),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('connect')
                                ->label('Connect WABA')
                                ->color('primary')
                                ->icon('heroicon-o-link')
                                ->action(fn () => $this->connectWaba()),
                        ]),
                    ]),

                Forms\Components\Section::make('How to Find Your WABA Details')
                    ->schema([
                        Forms\Components\Placeholder::make('instructions')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="prose prose-sm dark:prose-invert">
                                    <ol>
                                        <li>Go to <a href="https://business.facebook.com" target="_blank" class="text-primary-600">Meta Business Manager</a></li>
                                        <li>Navigate to <strong>Business Settings</strong> → <strong>Accounts</strong> → <strong>WhatsApp Accounts</strong></li>
                                        <li>Select your WhatsApp Business Account</li>
                                        <li>Copy the <strong>WhatsApp Business Account ID</strong> (WABA ID)</li>
                                        <li>Go to <strong>Phone Numbers</strong> to find your phone number</li>
                                        <li>For the Access Token, go to <strong>System Users</strong> → Create a token with WhatsApp permissions</li>
                                    </ol>
                                </div>
                            ')),
                    ])
                    ->collapsible()
                    ->collapsed(true),
            ])
            ->statePath('data');
    }

    public function connectWaba(): void
    {
        $data = $this->form->getState();

        $gymId = Filament::getTenant()?->id;

        if (! $gymId) {
            Notification::make()
                ->title('Error')
                ->body('No tenant found.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->whatsappAccount) {
            Notification::make()
                ->title('No Account Found')
                ->body('Please create a WhatsApp subaccount first.')
                ->danger()
                ->send();

            return;
        }

        try {
            $result = app(TwilioAccountManager::class)->connectManualWaba(
                $gymId,
                $data['waba_id'],
                $data['phone_number'],
                $data['meta_access_token'] ?? null
            );

            if ($result['success']) {
                $this->whatsappAccount->refresh();

                Notification::make()
                    ->title('WABA Connected')
                    ->body('Your WhatsApp Business Account has been connected successfully.')
                    ->success()
                    ->send();

                Log::info('Manual WABA connected', [
                    'gym_id' => $gymId,
                    'waba_id' => $data['waba_id'],
                ]);
            } else {
                throw new \Exception($result['error'] ?? 'Connection failed');
            }
        } catch (\Exception $e) {
            Log::error('Manual WABA connection failed', [
                'gym_id' => $gymId,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Connection Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function disconnectWaba(): void
    {
        if (! $this->whatsappAccount) {
            return;
        }

        $this->whatsappAccount->update([
            'waba_id' => null,
            'waba_mode' => null,
            'waba_status' => null,
            'meta_access_token' => null,
            'embedded_signup_completed' => false,
        ]);

        Notification::make()
            ->title('WABA Disconnected')
            ->body('Your WABA connection has been removed. You can reconnect or use Embedded Signup.')
            ->warning()
            ->send();

        $this->mount();
    }

    public function getGymId(): int|string|null
    {
        return Filament::getTenant()?->id;
    }
}
