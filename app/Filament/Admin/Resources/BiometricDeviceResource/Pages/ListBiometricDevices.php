<?php

namespace App\Filament\Admin\Resources\BiometricDeviceResource\Pages;

use App\Filament\Admin\Resources\BiometricDeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBiometricDevices extends ListRecords
{
    protected static string $resource = BiometricDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Create action
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->label('Add Device')
                ->color('primary'),
        ];
    }
}
