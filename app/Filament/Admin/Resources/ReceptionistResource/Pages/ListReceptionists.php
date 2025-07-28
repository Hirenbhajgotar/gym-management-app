<?php

namespace App\Filament\Admin\Resources\ReceptionistResource\Pages;

use App\Filament\Admin\Clusters\Permissions\Pages\ReceptionistPermissionsPage;
use App\Filament\Admin\Resources\ReceptionistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReceptionists extends ListRecords
{
    protected static string $resource = ReceptionistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('managePermissions')
                ->label('Manage Permissions')
                ->color('info')
                ->icon('heroicon-o-key')
                ->outlined()
                ->url(fn(): string => ReceptionistPermissionsPage::getUrl()),


            Actions\CreateAction::make(),
        ];
    }
}
