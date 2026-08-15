<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Filament\Admin\WhatsAppSetup\Actions\ConnectExistingWabaAction;
use App\Filament\Admin\WhatsAppSetup\Actions\RegisterWabaPhoneAction;
use App\Filament\Admin\WhatsAppSetup\Actions\StartEmbeddedSignupAction;
use Exception;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\HtmlString;
use Notification;

class WabaAccountStep
{
    public static function make(WhatsAppSetup $page): Step
    {
        return Step::make('WhatsApp Business')
            ->visible(fn () => ($page->data['provider_type'] ?? $page->selectedProvider) === WhatsAppProviderTypeEnum::WABA->value)
            ->schema([
                Section::make('Connect Your WhatsApp Business')->schema([
                    Radio::make('waba_connection_method')->label('Connection Method')
                        ->options(['embedded_signup' => 'Embedded Signup (Recommended)', 'manual_connect' => 'Manual Connect'])
                        ->descriptions(['embedded_signup' => "Use Meta's embedded signup flow.", 'manual_connect' => 'Enter your existing WABA credentials manually.'])
                        ->inline()
                        ->inlineLabel(false)
                        ->default('embedded_signup')->live()
                        ->disabled($page->accountStatus !== null),

                    Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.waba-connection-info')->render()))
                        ->visible(fn (Get $get) => $get('waba_connection_method') === 'embedded_signup' && ! $page->isWabaConnected()),

                    Placeholder::make('waba_pending_message')->hiddenLabel()->content(fn () => new HtmlString(
                        view('filament.admin.whatsapp-setup.waba-pending-message')->render()
                    ))->visible(fn () => $page->isWabaPending()),

                    Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.waba-success-message')->render()))
                        ->visible(fn () => session()->has('success')),

                    Actions::make([
                        Action::make('connectViaFacebook')->label('Connect via Facebook')->color('success')->icon('heroicon-o-arrow-right')
                            ->action('startEmbeddedSignupAction')
                            ->extraAttributes([
                                'wire:loading.attr' => 'disabled',
                                'wire:target' => 'data.waba_connection_method',
                            ])
                            ->disabled($page->accountStatus !== null)
                            ->visible(fn (Get $get) => $get('waba_connection_method') === 'embedded_signup' && ! $page->isWabaConnected()),
                    ]),

                    Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.waba-error-message')->render()))
                        ->visible(fn () => session()->has('error')),

                    // Manual Connect Section
                    Section::make('Connect Your Existing WABA')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('waba_id')
                                ->label('WABA ID')
                                ->required()
                                ->placeholder('123456789012345')
                                ->minLength(12)
                                ->maxLength(20),

                            TextInput::make('manual_phone_number')
                                ->label('WhatsApp Phone Number')
                                ->tel()
                                ->required()
                                ->placeholder('+1234567890')
                                ->rule('phone:INTERNATIONAL'),
                        ]),
                        TextInput::make('waba_phone_number_id')
                            ->label('WhatsApp Phone Number ID')
                            ->required()
                            ->placeholder('123456789012345')
                            ->minLength(12)
                            ->maxLength(20),

                        TextInput::make('meta_access_token')
                            ->label('Meta Access Token')
                            ->required()
                            ->password(),

                        Actions::make([
                            Action::make('connectExistingWaba')->label('Connect WABA')->color('primary')->icon('heroicon-o-link')
                                ->action('connectExistingWabaAction')
                                ->visible(fn (Get $get) => $get('waba_connection_method') === 'manual_connect' && ! $page->isWabaConnected()),
                        ]),
                    ])->visible(fn (Get $get) => $get('waba_connection_method') === 'manual_connect' && ! $page->isWabaConnected()),

                    Section::make('How to Find Your WABA Details')->schema([
                        Placeholder::make('waba_instructions')->hiddenLabel()->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.waba-instructions')->render())),
                    ])->collapsible()->collapsed(true)->visible(fn (Get $get) => $get('waba_connection_method') === 'manual_connect' && ! $page->isWabaConnected()),
                ])->visible(fn () => ! $page->isWabaConnected()),

                Placeholder::make('waba_connected')->hiddenLabel()->content(fn () => new HtmlString(
                    view('filament.admin.whatsapp-setup.waba-connected', ['verificationStatus' => $page->verificationStatus])->render()
                ))->visible(fn () => $page->isWabaConnected()),

                Actions::make([
                    Action::make('registerWabaPhone')
                        ->label('Sync & Register Phone Number')
                        ->color('primary')
                        ->icon('heroicon-o-phone-arrow-up-right')
                        ->modalHeading('Register & Activate WhatsApp Phone Number')
                        ->modalDescription('Enter your 6-digit Meta 2-Step Verification PIN if enabled in Meta Business Manager. Default is 123456.')
                        ->form([
                            TextInput::make('pin')
                                ->label('6-Digit Verification PIN')
                                ->placeholder('123456')
                                ->default('123456')
                                ->required()
                                ->minLength(6)
                                ->maxLength(6),
                        ])
                        ->action(function (array $data) use ($page) {
                            $pin = is_string($data['pin']) ? $data['pin'] : '123456';
                            (new RegisterWabaPhoneAction($page))($pin);
                        }),
                ])->visible(fn () => $page->isWabaConnected()),
            ])
            ->afterValidation(function () use ($page) {
                // Skip if account is already verified, in review, or disabled
                if ($page->whatsappAccount && (
                    $page->whatsappAccount->isVerified() ||
                    $page->whatsappAccount->isInReview() ||
                    $page->whatsappAccount->isWabaRestricted() ||
                    $page->whatsappAccount->isWabaSuspended() ||
                    $page->whatsappAccount->isWabaBlocked() ||
                    $page->whatsappAccount->isPendingReview() ||
                    $page->whatsappAccount->isNotVerified()
                )) {
                    return;
                }

                try {
                    if (($page->data['waba_connection_method'] ?? null) === 'embedded_signup') {
                        (new StartEmbeddedSignupAction($page))();
                    } elseif (($page->data['waba_connection_method'] ?? null) === 'manual_connect') {
                        (new ConnectExistingWabaAction($page))();
                    }
                } catch (Exception $e) {
                    Notification::make()->title('Setup Failed')->body($e->getMessage())->danger()->send();
                    throw new Halt();
                }
            });
    }
}
