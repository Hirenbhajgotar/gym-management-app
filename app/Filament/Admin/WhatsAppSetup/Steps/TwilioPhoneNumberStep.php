<?php

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\WhatsAppSetup\Actions\RegisterOwnNumberAction;
use App\Filament\Admin\WhatsAppSetup\Actions\VerifyOwnNumberAction;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\HtmlString;
use libphonenumber\PhoneNumberType;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class TwilioPhoneNumberStep
{
    public static function make($page): Step
    {
        return Step::make('Phone Number')
            ->visible(fn () => ($page->data['provider_type'] ?? $page->selectedProvider) === WhatsAppProviderTypeEnum::TWILIO->value && ! $page->numberPurchased)
            ->schema([
                // Radio::make('phone_number_source')->label('How would you like to get your phone number?')
                //     ->options(['purchase_twilio' => 'Purchase from Twilio', 'use_own_number' => 'Use My Own Number'])
                //     ->inline(true)
                //     ->inlineLabel(false)
                //     ->default('purchase_twilio')
                //     ->live(),

                Section::make('Purchase from Twilio')->schema([
                    Select::make('search_country')->label('Country')->options(['US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'IN' => 'India'])->default('US'),

                    Actions::make([
                        Action::make('search')
                            ->label('Search Numbers')
                            ->icon('heroicon-o-magnifying-glass')
                            ->action('searchNumbers')
                            ->extraAttributes([
                                'wire:loading.attr' => 'disabled',
                                'wire:target' => 'selectNumber,searchNumbers',
                            ]),
                    ]),

                    Placeholder::make('results')->hiddenLabel()->visible(fn () => $page->availableNumbersLoaded)
                        ->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.search-results', ['numbers' => $page->availableNumbers]))),
                ])
                // ->visible(fn (Get $get) => $get('phone_number_source') === 'purchase_twilio')
                ,

                // Section::make('Use My Own Number')->schema([
                //     PhoneInput::make('own_phone_number')
                //         ->label('Your Phone Number')
                //         ->required()
                //         ->disabled($page->ownNumberRegistered)
                //         ->defaultCountry('IN')
                //         ->initialCountry('IN')
                //         ->validateFor(
                //             country: ['IN', 'PK', 'BD', 'US'],
                //             type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE],
                //             lenient: true,
                //         )
                //         ->validationMessages([
                //             'required' => 'Please enter your phone number',
                //             'phone' => 'Please enter a valid phone number',
                //         ]),

                //     Actions::make([
                //         Action::make('register')->label('Register Number')->action(fn () => (new RegisterOwnNumberAction($page))())->visible(fn () => ! $page->ownNumberRegistered),
                //     ]),

                //     TextInput::make('otp_code')->label('Verification Code')->maxLength(6)->visible(fn () => $page->ownNumberRegistered && ! $page->numberPurchased),

                //     Actions::make([
                //         Action::make('verify')->label('Verify')->action(fn () => (new VerifyOwnNumberAction($page))())->visible(fn () => $page->ownNumberRegistered && ! $page->numberPurchased),
                //     ]),
                // ])->visible(fn (Get $get) => $get('phone_number_source') === 'use_own_number'),
            ]);
        // ->afterValidation(function () use ($page) {
        //     try {
        //         if ($page->data['phone_number_source'] === 'use_own_number') {
        //             (new RegisterOwnNumberAction($page))();
        //         }
        //     } catch (\Exception $e) {
        //         \Filament\Notifications\Notification::make()->title('Setup Failed')->body($e->getMessage())->danger()->send();
        //         throw new Halt;
        //     }
        // })
    }
}
