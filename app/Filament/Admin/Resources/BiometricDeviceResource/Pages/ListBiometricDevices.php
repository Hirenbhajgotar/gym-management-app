<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BiometricDeviceResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\BiometricDeviceResource;
use Filament\Resources\Pages\ListRecords;

class ListBiometricDevices extends ListRecords
{
    protected static string $resource = BiometricDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Create action
            CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->label('Add Device')
                ->color('primary'),
        ];
    }
}
