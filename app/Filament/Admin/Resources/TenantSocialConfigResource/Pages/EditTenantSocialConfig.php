<?php

namespace App\Filament\Admin\Resources\TenantSocialConfigResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use App\Jobs\ProcessSocialMediaLead;
use Filament\Notifications\Notification;
use App\Models\TenantSocialConfig;
use App\Filament\Admin\Resources\TenantSocialConfigResource;
use Filament\Resources\Pages\EditRecord;

class EditTenantSocialConfig extends EditRecord
{
    protected static string $resource = TenantSocialConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('test_webhook')
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
             * @var TenantSocialConfig $config
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
            dispatch(new ProcessSocialMediaLead($testData, $config->platform, 'test_' . uniqid()));

            Notification::make()
                ->title('Test webhook sent successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Test webhook failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
