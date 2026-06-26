<?php

namespace App\Filament\Admin\Resources\BiometricDeviceResource\Pages;

use App\Filament\Admin\Resources\BiometricDeviceResource;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Js;

class CreateBiometricDevice extends CreateRecord
{
    protected static string $resource = BiometricDeviceResource::class;

    protected ?string $generatedToken = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set created_by to the current authenticated user
        $data['created_by'] = auth()->id();
        $data['gym_id'] = $data['gym_id'] ?? Filament::getTenant()->id;

        // Auto-generate device token if not provided (model cast will hash it)
        if (blank($data['device_token'])) {
            $this->generatedToken = bin2hex(random_bytes(32));
            $data['device_token'] = $this->generatedToken; // Model cast handles hashing
        }

        $data['provider_config']['collection_id'] = 'flux_gym_' . Filament::getTenant()->id;
        $data['provider_config']['face_list_id'] = 'flux_gym_' . Filament::getTenant()->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return BiometricDeviceResource::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $notification = Notification::make()
            ->success()
            ->title('Device registered')
            ->body('The device has been added successfully.');

        if ($this->generatedToken) {
            $notification
                ->body('Device API token: ' . $this->generatedToken . '. Copy it now as you won\'t see it again!')
                ->actions([
                    NotificationAction::make('copy')
                        ->label('Copy to Clipboard')
                        ->icon('heroicon-m-clipboard')
                        ->link()
                        ->url('javascript:navigator.clipboard.writeText(' . Js::from($this->generatedToken) . ')')
                        ->close(),
                ])
                ->persistent();
        }

        return $notification;
    }
}
