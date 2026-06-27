<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\GymResource;
use Filament\Resources\Pages\ListRecords;

class ListGyms extends ListRecords
{
    protected static string $resource = GymResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
