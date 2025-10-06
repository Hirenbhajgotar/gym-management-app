<?php

namespace App\Filament\Admin\Resources\GymResource\Pages;

use App\Filament\Admin\Resources\GymResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGyms extends ListRecords
{
    protected static string $resource = GymResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
