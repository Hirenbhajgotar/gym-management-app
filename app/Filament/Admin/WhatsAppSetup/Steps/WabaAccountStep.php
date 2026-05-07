<?php

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\WhatsAppSetup\Actions\ConnectExistingWabaAction;
use App\Filament\Admin\WhatsAppSetup\Actions\StartEmbeddedSignupAction;
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
    public static function make($page): Step
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
                        ->default('embedded_signup')->live()->visible(fn () => ! $page->isWabaConnected()),

                    Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.waba-connection-info')))
                        ->visible(fn (Get $get) => $get('waba_connection_method') === 'embedded_signup' && ! $page->isWabaConnected()),

                    Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.waba-success-message')))
                        ->visible(fn () => session()->has('success')),

                    \Filament\Forms\Components\Actions::make([
                        Action::make('connectViaFacebook')->label('Connect via Facebook')->color('success')->icon('heroicon-o-arrow-right')
                            ->action('startEmbeddedSignupAction')
                            ->extraAttributes([
                                'wire:loading.attr' => 'disabled',
                                'wire:target' => 'data.waba_connection_method',
                            ])
                            ->visible(fn (Get $get) => $get('waba_connection_method') === 'embedded_signup' && ! $page->isWabaConnected()),
                    ]),

                    Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.waba-error-message')))
                        ->visible(fn () => session()->has('error')),

                    // Manual Connect Section
                    Section::make('Connect Your Existing WABA')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('waba_id')
                                ->label('WABA ID')
                                ->required()
                                ->placeholder('123456789012345')
                                ->regex('/^\d{15}$/'),

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
                            ->regex('/^\d{15}$/'),

                        TextInput::make('meta_access_token')
                            ->label('Meta Access Token')
                            ->required()
                            ->password(),

                        \Filament\Forms\Components\Actions::make([
                            Action::make('connectExistingWaba')->label('Connect WABA')->color('primary')->icon('heroicon-o-link')
                                ->action('connectExistingWabaAction'),
                        ]),
                    ])->visible(fn (Get $get) => $get('waba_connection_method') === 'manual_connect' && ! $page->isWabaConnected()),

                    Section::make('How to Find Your WABA Details')->schema([
                        Placeholder::make('waba_instructions')->hiddenLabel()->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.waba-instructions'))),
                    ])->collapsible()->collapsed(true)->visible(fn (Get $get) => $get('waba_connection_method') === 'manual_connect' && ! $page->isWabaConnected()),
                ])->visible(fn () => ! $page->isWabaConnected()),

                Placeholder::make('waba_connected')->hiddenLabel()->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.waba-connected')))
                    ->visible(fn () => $page->isWabaConnected()),
            ])
            ->afterValidation(function () use ($page) {
                try {
                    if ($page->data['waba_connection_method'] === 'embedded_signup') {
                        (new StartEmbeddedSignupAction($page))();
                    } elseif ($page->data['waba_connection_method'] === 'manual_connect') {
                        (new ConnectExistingWabaAction($page))();
                    }
                } catch (\Exception $e) {
                    Notification::make()->title('Setup Failed')->body($e->getMessage())->danger()->send();
                    throw new Halt;
                }
            });
    }
}
