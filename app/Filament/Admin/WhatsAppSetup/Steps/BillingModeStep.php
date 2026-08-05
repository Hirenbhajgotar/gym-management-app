<?php

declare(strict_types=1);

namespace App\Filament\Admin\WhatsAppSetup\Steps;

use App\Actions\WhatsAppAccount\UpdateBillingModeAction;
use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Models\WhatsAppAccount;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Log;
use Throwable;

class BillingModeStep
{
    public static function make(WhatsAppSetup $page): Step
    {
        return Step::make('Billing Mode')
            ->visible(fn () => ($page->data['provider_type'] ?? $page->selectedProvider) === WhatsAppProviderTypeEnum::WABA->value)
            ->schema([
                Section::make('Choose Your Billing Mode')
                    ->description('Select how you want to handle Meta WhatsApp charges')
                    ->schema([
                        Radio::make('billing_mode')
                            ->label('')
                            ->options(WhatsAppBillingModeEnum::class)
                            ->descriptions(WhatsAppBillingModeEnum::casesToDescriptionsArray())
                            ->default(WhatsAppBillingModeEnum::PAY_VIA_APP->value)
                            ->live()
                            ->afterStateUpdated(function ($state) use ($page) {
                                $stringValue = $state instanceof WhatsAppBillingModeEnum ? $state->value : (is_scalar($state) ? (string) $state : '');
                                $page->data['billing_mode'] = $stringValue;
                            }),
                    ]),
            ])
            ->afterValidation(function () use ($page) {
                $billingMode = $page->data['billing_mode'] ?? null;
                $providerType = $page->data['provider_type'] ?? $page->selectedProvider;

                if (! $providerType) {
                    Notification::make()
                        ->title('Error: No WhatsApp provider selected. Please select a WhatsApp provider.')
                        ->danger()
                        ->send();

                    throw new Halt();
                }

                $account = WhatsAppAccount::query()->firstOrCreate(
                    ['gym_id' => $page->getGymId()],
                    ['provider_type' => $providerType]
                );

                if (! $account) {
                    Notification::make()
                        ->title('Error: No WhatsApp account found. Please connect your account first.')
                        ->danger()
                        ->send();

                    throw new Halt();
                }

                if ($billingMode) {
                    try {
                        resolve(UpdateBillingModeAction::class)($account, WhatsAppBillingModeEnum::from($billingMode));
                    } catch (Throwable $th) {
                        Log::error('Failed to update billing mode. ' . $th->getMessage());
                        Notification::make()
                            ->title('Error: Failed to update billing mode.')
                            ->body($th->getMessage())
                            ->danger()
                            ->send();

                        throw new Halt();
                    }
                }
            });
    }
}
