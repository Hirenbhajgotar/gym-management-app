<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\UserResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
