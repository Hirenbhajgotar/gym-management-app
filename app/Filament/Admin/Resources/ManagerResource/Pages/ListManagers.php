<?php

namespace App\Filament\Admin\Resources\ManagerResource\Pages;

use App\Filament\Admin\Clusters\Permissions\Pages\ManagerPermissionsPage;
use App\Filament\Admin\Resources\ManagerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListManagers extends ListRecords
{
    protected static string $resource = ManagerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('managePermissions')
                ->label('Manage Permissions')
                ->color('info')
                ->icon('heroicon-o-key')
                ->outlined()
                ->url(fn(): string => ManagerPermissionsPage::getUrl()),


            Actions\CreateAction::make(),
        ];
    }
}
