<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdmsDeviceCommandResource\Pages;

use App\Filament\Admin\Resources\AdmsDeviceCommandResource;
use Filament\Resources\Pages\ListRecords;

class ListAdmsDeviceCommands extends ListRecords
{
    protected static string $resource = AdmsDeviceCommandResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
