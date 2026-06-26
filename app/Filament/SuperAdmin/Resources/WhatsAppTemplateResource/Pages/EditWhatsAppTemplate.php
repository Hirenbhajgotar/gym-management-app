<?php

namespace App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource;
use App\Jobs\PushWhatsAppTemplateToGymJob;
use App\Models\Gym;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Builder;

class EditWhatsAppTemplate extends EditRecord
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('push')
                ->label('Push to All Gyms')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Push Template to All Gyms')
                ->modalDescription(fn () => "This will copy \"{$this->getRecord()->display_name}\" to all gyms with active users that don't already have a template with the same name. Existing templates with the same name will NOT be overwritten.")
                ->before(function () {
                    $gymCount = Gym::query()->whereHas('users', function (Builder $query) {
                        $query->where('users.is_active', true);
                    })->count();

                    dispatch(new PushWhatsAppTemplateToGymJob($this->getRecord()->id));

                    Notification::make()
                        ->success()
                        ->title('Template Push Started')
                        ->body("Template is being pushed to {$gymCount} active gyms in the background.")
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
