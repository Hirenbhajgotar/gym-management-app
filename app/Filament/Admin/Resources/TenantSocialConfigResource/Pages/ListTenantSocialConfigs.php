<?php

namespace App\Filament\Admin\Resources\TenantSocialConfigResource\Pages;

use App\Filament\Admin\Resources\TenantSocialConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantSocialConfigs extends ListRecords
{
    protected static string $resource = TenantSocialConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->color('primary'),
        ];
    }
}
