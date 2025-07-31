<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\Pages;

use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource;
use App\Jobs\ImportDefaultPermissionsJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPermissionSettings extends ListRecords
{
    protected static string $resource = PermissionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importPermissions')
                ->label('Import Permissions')
                ->color('info')
                ->icon('heroicon-o-cloud-arrow-down')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading('Import Default Permissions')
                ->modalDescription('This will import default permissions for all roles based on their permission settings. This process will run in the background.')
                ->action(function (): void {
                    ImportDefaultPermissionsJob::dispatch(null, auth()->id());

                    Notification::make()
                        ->title('Permissions Import Queued')
                        ->body('Default permissions are being imported for all roles. This may take a few minutes.')
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
