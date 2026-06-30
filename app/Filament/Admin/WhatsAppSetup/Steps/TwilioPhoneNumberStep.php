<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use App\Enums\WhatsAppProviderTypeEnum;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

class TwilioPhoneNumberStep
{
    public static function make($page): Step
    {
        return Step::make('Phone Number')
            ->visible(fn () => ($page->data['provider_type'] ?? $page->selectedProvider) === WhatsAppProviderTypeEnum::TWILIO->value && ! $page->numberPurchased)
            ->schema([
                Section::make('Purchase from Twilio')->schema([
                    Select::make('search_country')
                        ->label('Country')
                        ->options(['US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'IN' => 'India'])
                        ->default('US')
                        ->disabled($page->whatsappAccount?->isVerified() || $page->whatsappAccount?->isInReview()),

                    Actions::make([
                        Action::make('search')
                            ->label('Search Numbers')
                            ->icon('heroicon-o-magnifying-glass')
                            ->action('searchNumbers')
                            ->extraAttributes([
                                'wire:loading.attr' => 'disabled',
                                'wire:target' => 'selectNumber,searchNumbers',
                            ])
                            ->disabled($page->whatsappAccount?->isVerified() || $page->whatsappAccount?->isInReview()),
                    ]),

                    Placeholder::make('results')->hiddenLabel()->visible(fn () => $page->availableNumbersLoaded)
                        ->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.search-results', [
                            'numbers' => $page->availableNumbers,
                        ])->render())),
                ]),
            ]);
    }
}
