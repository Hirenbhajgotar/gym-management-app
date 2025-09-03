<?php

namespace App\Filament\Admin\Resources\DealResource\Pages;

use App\Filament\Admin\Resources\DealResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeals extends ListRecords
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
