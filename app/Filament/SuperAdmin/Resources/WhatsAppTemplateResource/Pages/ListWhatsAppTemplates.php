<?php

namespace App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource;
use App\Services\WhatsAppTemplateService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppTemplates extends ListRecords
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncFromTwilio')
                ->label('Sync from Twilio')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sync Templates from Twilio Main Account')
                ->modalDescription('This will fetch all WhatsApp templates from the main Twilio account and save them as system-wide templates (gym_id=null). Only approved templates with valid content will be imported.')
                ->action(function (WhatsAppTemplateService $service) {
                    $result = $service->syncTemplatesFromTwilio();

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Sync Completed')
                            ->body('Successfully synced ' . ($result['synced_count'] ?? 0) . ' templates.')
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Sync Failed')
                            ->body($result['error'] ?? 'Unknown error occurred.')
                            ->send();
                    }
                }),

            Actions\CreateAction::make()
                ->label('New Template')
                ->icon('heroicon-o-plus'),
        ];
    }
}
