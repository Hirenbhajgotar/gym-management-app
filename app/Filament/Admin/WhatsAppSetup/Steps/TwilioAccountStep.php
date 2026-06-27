<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use Filament\Notifications\Notification;
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
                        TextInput::make('business_name')
                            ->label('Business Name')
                            ->required()
                            ->disabled($page->accountCreated || ($page->whatsappAccount?->isVerified() || $page->whatsappAccount?->isInReview())),

                        TextInput::make('business_email')
                            ->label('Business Email')
                            ->email()
                            ->required()
                            ->disabled($page->accountCreated || ($page->whatsappAccount?->isVerified() || $page->whatsappAccount?->isInReview())),
                    ]),

                    Textarea::make('business_description')
                        ->label('Business Description')
                        ->rows(2)
                        ->disabled($page->accountCreated || ($page->whatsappAccount?->isVerified() || $page->whatsappAccount?->isInReview())),
                ]),
            ])
            ->afterValidation(function () use ($page) {
                // Skip if account is already verified, in review, or disabled
                if ($page->whatsappAccount && (
                    $page->whatsappAccount->isVerified() ||
                    $page->whatsappAccount->isInReview() ||
                    $page->whatsappAccount->isWabaRestricted() ||
                    $page->whatsappAccount->isWabaSuspended() ||
                    $page->whatsappAccount->isWabaBlocked()
                )) {
                    return;
                }

                try {
                    (new ProcessTwilioAccountAction($page))();
                } catch (\Exception $e) {
                    Notification::make()->title('Setup Failed')->body($e->getMessage())->danger()->send();
                    throw new Halt;
                }
            });
    }
}
