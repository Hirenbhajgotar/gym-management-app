<?php

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\WhatsAppSetup\Actions\ProcessTwilioAccountAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;

class TwilioAccountStep
{
    public static function make($page): Step
    {
        return Step::make('Twilio Account')
            ->label('Account')
            ->visible(fn () => ($page->data['provider_type'] ?? $page->selectedProvider) === WhatsAppProviderTypeEnum::TWILIO->value)
            ->schema([
                Section::make('Business Information')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('business_name')->label('Business Name')->required()->disabled($page->accountCreated),
                        TextInput::make('business_email')->label('Business Email')->email()->required()->disabled($page->accountCreated),
                    ]),
                    Textarea::make('business_description')->label('Business Description')->rows(2),
                ]),
            ])
            ->afterValidation(function () use ($page) {
                try {
                    (new ProcessTwilioAccountAction($page))();
                } catch (\Exception $e) {
                    \Filament\Notifications\Notification::make()->title('Setup Failed')->body($e->getMessage())->danger()->send();
                    throw new Halt;
                }
            });
    }
}
