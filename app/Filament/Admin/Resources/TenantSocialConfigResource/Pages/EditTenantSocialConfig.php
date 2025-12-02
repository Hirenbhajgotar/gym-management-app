<?php

namespace App\Filament\Admin\Resources\TenantSocialConfigResource\Pages;

use App\Filament\Admin\Resources\TenantSocialConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantSocialConfig extends EditRecord
{
    protected static string $resource = TenantSocialConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('test_webhook')
                ->label('Test Webhook')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->action(fn () => $this->testWebhook())
                ->requiresConfirmation()
                ->modalHeading('Test Webhook')
                ->modalDescription('This will send a test webhook to verify the configuration.')
                ->modalSubmitActionLabel('Send Test'),
        ];
    }

    /**
     * Test webhook configuration
     */
    private function testWebhook(): void
    {
        try {
            /**
             * @var \App\Models\TenantSocialConfig $config
             */
            $config = $this->getRecord();

            // Create test lead data
            $testData = [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => '+919999999999',
                'notes' => 'Test webhook from Filament admin',
                'platform' => $config->platform,
                'account_id' => $config->account_id,
            ];

            // Dispatch test job
            \App\Jobs\ProcessSocialMediaLead::dispatch(
                $testData,
                $config->platform,
                'test_' . uniqid()
            );

            \Filament\Notifications\Notification::make()
                ->title('Test webhook sent successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Test webhook failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
