<?php

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use App\Enums\WhatsAppProviderTypeEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

class ProviderSelectionStep
{
    public static function make($page): Step
    {
        return Step::make('Select Provider')
            ->schema([
                Section::make('Choose Your WhatsApp Provider')
                    ->description('Select how you want to set up WhatsApp messaging')
                    ->schema([
                        Placeholder::make('')->content(fn () => new HtmlString(view('filament.admin.whatsapp-setup.provider-cards', ['selectedProvider' => $page->selectedProvider])->render())),

                        Radio::make('provider_type')
                            ->label('')
                            ->options(WhatsAppProviderTypeEnum::class)
                            ->descriptions(WhatsAppProviderTypeEnum::toDescriptionsArray())
                            ->default(WhatsAppProviderTypeEnum::TWILIO->value)
                            ->live()
                            ->afterStateUpdated(function ($state) use ($page) {
                                $stringValue = $state instanceof WhatsAppProviderTypeEnum ? $state->value : (string) $state;
                                $page->selectedProvider = $stringValue;
                                $page->data['provider_type'] = $stringValue;
                                if (isset($page->form)) {
                                    $page->form->fill($page->data);
                                }
                            })
                            ->disabled($page->accountCreated || ($page->whatsappAccount?->isVerified() || $page->whatsappAccount?->isInReview())),
                    ]),
            ]);
    }
}
