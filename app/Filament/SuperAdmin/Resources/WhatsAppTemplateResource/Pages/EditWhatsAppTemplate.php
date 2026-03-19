<?php

namespace App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource;
use App\Jobs\PushWhatsAppTemplateToGymJob;
use App\Models\Gym;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppTemplate extends EditRecord
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('push')
                ->label('Push to All Gyms')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Push Template to All Gyms')
                ->modalDescription(fn () => "This will copy \"{$this->getRecord()->display_name}\" to all gyms with active users that don't already have a template with the same name. Existing templates with the same name will NOT be overwritten.")
                ->before(function () {
                    $gymCount = Gym::whereHas('users', function ($query) {
                        $query->where('users.is_active', true);
                    })->count();

                    PushWhatsAppTemplateToGymJob::dispatch($this->getRecord()->id);

                    Notification::make()
                        ->success()
                        ->title('Template Push Started')
                        ->body("Template is being pushed to {$gymCount} active gyms in the background.")
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
