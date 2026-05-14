<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource;
use App\Services\Twilio\TwilioTemplateService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;

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
                ->action(function (TwilioTemplateService $service) {
                    $result = $service->syncTemplatesFromTwilioMasterAccount();

                    if ($result->success === true && ! empty($result->errorCount) && $result->errorCount > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Sync Partially Completed')
                            ->body('Successfully synced ' . ($result->syncedCount ?? 0) . ' templates, but ' . $result->errorCount . ' failed. ' . (isset($result->errors) ? collect($result->errors)->implode(', ') : ''))
                            ->send();
                    } elseif ($result->success) {
                        Notification::make()
                            ->success()
                            ->title('Sync Completed')
                            ->body('Successfully synced ' . ($result->syncedCount ?? 0) . ' templates.')
                            ->send();
                    } else {
                        Log::error('Template sync failed', ['error' => $result->error]);
                        Notification::make()
                            ->danger()
                            ->title('Sync Failed')
                            ->body($result->error ?? 'Something is wrong please try again later.')
                            ->send();
                    }
                }),

            Actions\CreateAction::make()
                ->label('New Template')
                ->icon('heroicon-o-plus'),
        ];
    }
}
