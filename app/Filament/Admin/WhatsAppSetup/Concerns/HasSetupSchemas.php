<?php

namespace App\Filament\Admin\WhatsAppSetup\Concerns;

use App\Enums\WhatsAppProviderTypeEnum;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\HtmlString;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

/**
 * Form schema building methods for the WhatsApp setup wizard.
 * Each method returns a Form schema array for a specific wizard step or view state.
 */
trait HasSetupSchemas
{
    protected function getWhatsAppFormSchema(): array
    {
        return match ($this->viewState) {
            'onboarding' => $this->getOnboardingSchema(),
            'setup' => $this->getSetupSchema(),
            'active' => $this->getActiveSchema(),
            'error' => $this->getErrorSchema(),
            default => [],
        };
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

    private function getOnboardingSchema(): array
    {
        return [
            Section::make('WhatsApp Business Integration')
                ->description('Connect your gym with members through WhatsApp')
                ->schema([
                    Placeholder::make('')
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

                    Actions::make([
                        Action::make('start_setup')
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
            Wizard::make([
                $this->getProviderSelectionStep(),
                $this->getAccountStep(),
                $this->getPhoneNumberStep(),
                $this->getTemplatesStep(),
                $this->getWelcomeCreditsStep(),
            ])
                ->persistStepInQueryString()
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

    private function getProviderSelectionStep(): Step
    {
        return Step::make('Select Provider')
            ->label('Select Provider')
            ->schema([
                Section::make('Choose Your WhatsApp Provider')
                    ->description('Select how you want to set up WhatsApp messaging')
                    ->schema([
                        Placeholder::make('')
                            ->content(fn () => new HtmlString('
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4">
                                    <div class="p-4 rounded-lg border-2 ' . ($this->selectedProvider === WhatsAppProviderTypeEnum::WABA->value ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700') . '">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="font-semibold ' . ($this->selectedProvider === WhatsAppProviderTypeEnum::WABA->value ? 'text-primary-900 dark:text-primary-400' : 'text-gray-900 dark:text-gray-100') . '">Meta WABA</span>
                                            ' . ($this->selectedProvider === WhatsAppProviderTypeEnum::WABA->value ? '<span class="' . ($this->selectedProvider === WhatsAppProviderTypeEnum::WABA->value ? 'text-primary-900 dark:text-primary-400' : 'text-gray-900 dark:text-gray-100') . '">✓ Selected</span>' : '') . '
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
                                            <span class="font-semibold ' . ($this->selectedProvider === WhatsAppProviderTypeEnum::TWILIO->value ? 'text-primary-900 dark:text-primary-400' : 'text-gray-900 dark:text-gray-100') . '">Twilio</span>
                                            ' . ($this->selectedProvider === WhatsAppProviderTypeEnum::TWILIO->value ? '<span class="' . ($this->selectedProvider === WhatsAppProviderTypeEnum::TWILIO->value ? 'text-primary-900 dark:text-primary-400' : 'text-gray-900 dark:text-gray-100') . '">✓ Selected</span>' : '') . '
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
                        Radio::make('provider_type')
                            ->label('')
                            ->options(WhatsAppProviderTypeEnum::class)
                            ->descriptions(WhatsAppProviderTypeEnum::toDescriptionsArray())
                            ->default(WhatsAppProviderTypeEnum::TWILIO->value)
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $stringValue = $state instanceof WhatsAppProviderTypeEnum
                                    ? $state->value
                                    : (string) $state;

                                $this->selectedProvider = $stringValue;
                                $this->data['provider_type'] = $stringValue;

                                if (isset($this->form)) {
                                    $this->form->fill($this->data);
                                }
                            })
                            ->disabled($this->accountCreated),
                    ])
                    ->visible(fn () => ! $this->accountCreated || ! $this->whatsappAccount?->hasProvider()),
            ])
            ->afterValidation(function () {
                $data = $this->form->getState();
                $providerState = $data['provider_type'] ?? null;
                $this->selectedProvider = $providerState instanceof WhatsAppProviderTypeEnum
                    ? $providerState->value
                    : (string) ($providerState ?? WhatsAppProviderTypeEnum::WABA->value);
            });
    }

    private function getAccountStep(): Step
    {
        $formProvider = $this->data['provider_type'] ?? $this->selectedProvider;
        $providerValue = $formProvider instanceof WhatsAppProviderTypeEnum
            ? $formProvider->value
            : (string) $formProvider;

        return $providerValue === WhatsAppProviderTypeEnum::WABA->value
            ? $this->getWabaAccountStep()
            : $this->getTwilioAccountStep();
    }

    private function getPhoneNumberStep(): Step
    {
        $formProvider = $this->data['provider_type'] ?? $this->selectedProvider;
        $providerValue = $formProvider instanceof WhatsAppProviderTypeEnum
            ? $formProvider->value
            : (string) $formProvider;

        return $providerValue === WhatsAppProviderTypeEnum::WABA->value
            ? $this->getWabaPhoneNumberStep()
            : $this->getTwilioPhoneNumberStep();
    }

    private function getTwilioAccountStep(): Step
    {
        return Step::make('Twilio Account')
            ->label('Twilio Account')
            ->schema([
                Section::make('Business Information')
                    ->description('Enter your business details for the Twilio subaccount')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('business_name')
                                ->label('Business Name')
                                ->required()
                                ->disabled($this->accountCreated),
                            TextInput::make('business_email')
                                ->label('Business Email')
                                ->email()
                                ->required()
                                ->disabled($this->accountCreated),
                        ]),
                        Textarea::make('business_description')
                            ->label('Business Description')
                            ->rows(2)
                            ->placeholder('Describe your gym business...'),
                    ]),
            ])
            ->afterValidation(function () {
                try {
                    $this->processTwilioAccountSetup();
                } catch (\Exception $e) {
                    $this->handleSetupError($e, 'Failed to process Twilio account setup');
                    throw new Halt;
                }
            });
    }

    private function getTwilioPhoneNumberStep(): Step
    {
        return Step::make('Phone Number')
            ->label('Phone Number')
            ->schema([
                Section::make('Get a Phone Number')
                    ->description('Choose how to get your WhatsApp phone number')
                    ->schema([
                        Radio::make('phone_number_source')
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

    private function getWabaAccountStep(): Step
    {
        return Step::make('WhatsApp Business')
            ->label('WhatsApp Business')
            ->schema([
                Section::make('Connect Your WhatsApp Business')
                    ->description('Set up your Meta WhatsApp Business Account')
                    ->schema([
                        Radio::make('waba_connection_method')
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

                        Placeholder::make('')
                            ->content(new HtmlString('
                                <div class="mt-4 p-4 bg-blue-50 dark:bg-gray-800 border border-blue-200 dark:border-blue-400 rounded-lg">
                                    <h4 class="font-medium text-blue-800 dark:text-blue-400 mb-2">Complete WABA Setup</h4>
                                    <p class="text-sm text-blue-700 dark:text-blue-300 mb-4">Click the button below to connect your WhatsApp Business Account through Facebook.</p>
                                </div>
                            '))
                            ->visible(fn (Get $get): bool => $get('waba_connection_method') === 'embedded_signup' && ! $this->isWabaConnected()),

                        Placeholder::make('')
                            ->content(new HtmlString('
                                <div class="mt-4 p-4 bg-primary-500 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 rounded-lg flex items-start gap-3">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-800/50 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-green-600 dark:text-green-400">Connected Successfully</p>
                                        <p class="text-sm text-green-500 dark:text-green-400 mt-1">' . session('success') . '</p>
                                    </div>
                                </div>
                            '))
                            ->visible(fn (): bool => session()->has('success')),

                        Actions::make([
                            Action::make('connectViaFacebook')
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

                        Placeholder::make('')
                            ->content(new HtmlString('
                                <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg flex items-start gap-3">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-800/50 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-red-800 dark:text-red-300">Connection Error</p>
                                        <p class="text-sm text-red-700 dark:text-gray-700 mt-1">' . session('error') . '</p>
                                    </div>
                                </div>
                            '))
                            ->visible(fn (): bool => session()->has('error')),

                        Section::make('Connect Your Existing WABA')
                            ->description('Enter your WhatsApp Business Account details from Meta Business Manager.')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('waba_id')
                                        ->label('WABA ID')
                                        ->helperText('Your WhatsApp Business Account ID from Meta Business Manager (15 digits)')
                                        ->required()
                                        ->placeholder('123456789012345')
                                        ->regex('/^\d{15}$/')
                                        ->validationMessages([
                                            'required' => 'WABA ID is required',
                                            'regex' => 'WABA ID must be exactly 15 digits (e.g., 123456789012345)',
                                        ]),

                                    TextInput::make('manual_phone_number')
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

                                TextInput::make('waba_phone_number_id')
                                    ->label('WhatsApp Phone Number ID')
                                    ->helperText('The Phone Number ID from Meta Business Manager (15 digits, e.g., 123456789012345)')
                                    ->required()
                                    ->placeholder('123456789012345')
                                    ->regex('/^\d{15}$/')
                                    ->validationMessages([
                                        'required' => 'Phone Number ID is required',
                                        'regex' => 'Phone Number ID must be exactly 15 digits',
                                    ]),

                                TextInput::make('meta_access_token')
                                    ->label('Meta Access Token')
                                    ->helperText('Required. Permanent access token from Meta Business Manager to verify your account.')
                                    ->required()
                                    ->password(),

                                Actions::make([
                                    Action::make('connectExistingWaba')
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

                        Section::make('How to Find Your WABA Details')
                            ->schema([
                                Placeholder::make('waba_instructions')
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

                Placeholder::make('waba_connected')
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
                    $this->handleSetupError($e, 'Failed to process WABA setup');
                    throw new Halt;
                }
            });
    }

    private function getWabaPhoneNumberStep(): Step
    {
        return Step::make('Phone Number')
            ->label('Phone Number')
            ->schema([
                Placeholder::make('phone_info')
                    ->hiddenLabel()
                    ->content(new HtmlString('
                        <div class="py-4">
                            <p class="text-gray-600 dark:text-gray-400">Your phone number will be handled through the WhatsApp Business Account connected in the previous step.</p>
                        </div>
                    ')),
            ])
            ->visible(fn (Get $get): bool => $get('provider_type') === 'twilio' && ! $this->isWabaConnected());
    }

    private function getTemplatesStep(): Step
    {
        return Step::make('Templates')
            ->label('Default Templates')
            ->schema([
                Placeholder::make('')
                    ->content(new HtmlString('
                        <div class="mb-4">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">Setting up default message templates</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">We will create 8 utility templates for common gym communications. These will be submitted for Meta approval.</p>
                        </div>
                    ')),
                Actions::make([
                    Action::make('createTemplates')
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

    private function getWelcomeCreditsStep(): Step
    {
        return Step::make('Welcome Credits')
            ->label('Welcome Credits')
            ->schema([
                Placeholder::make('')
                    ->content(new HtmlString('
                        <div class="text-center py-6">
                            <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mb-4">
                                <span class="text-2xl">🎁</span>
                            </div>
                            <h3 class="font-medium text-lg text-gray-900 dark:text-gray-100 mb-2">50 Free Welcome Credits!</h3>
                            <p class="text-gray-500 dark:text-gray-400">Claim your 50 free WhatsApp message credits to get started.</p>
                        </div>
                    ')),
                Actions::make([
                    Action::make('claimWelcome')
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
            Section::make('WhatsApp Business Status')
                ->description('Your WhatsApp Business integration is active.')
                ->schema([
                    Grid::make(2)->schema([
                        Placeholder::make('status')
                            ->label('Account Status')
                            ->content(new HtmlString('
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200">
                                    ✓ Active
                                </span>
                            ')),

                        Placeholder::make('phone_number')
                            ->label('WhatsApp Number')
                            ->content($this->phoneNumber?->phone_number ?? 'N/A'),

                        Placeholder::make('account_created')
                            ->label('Account Created')
                            ->content($this->whatsappAccount?->created_at?->format('M d, Y g:i A') ?? 'N/A'),

                        Placeholder::make('credits')
                            ->label('Message Credits')
                            ->content(($this->creditBalance?->remaining_credits ?? 0) . ' remaining'),
                    ]),

                    Actions::make([
                        Action::make('testMessage')
                            ->label('Send Test Message')
                            ->color('info')
                            ->form([
                                PhoneInput::make('test_phone')
                                    ->label('Phone Number')
                                    ->required(),
                            ])
                            ->action('sendTestMessage'),

                        Action::make('viewTemplates')
                            ->label('Manage Templates')
                            ->url(route('filament.admin.resources.whats-app-templates.index', ['tenant' => $this->getTenantSlug()])),
                    ]),
                ]),
        ];
    }

    private function getErrorSchema(): array
    {
        return [
            Section::make('Setup Incomplete')
                ->description('There was an issue with your WhatsApp setup.')
                ->schema([
                    Placeholder::make('error_message')
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
                    Actions::make([
                        Action::make('retry')
                            ->label('Retry Setup')
                            ->color('primary')
                            ->action('retrySetup'),
                    ])->alignCenter(),
                ]),
        ];
    }
}
