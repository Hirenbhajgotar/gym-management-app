<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\StateResource\Pages;

use App\Filament\SuperAdmin\Resources\StateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditState extends EditRecord
{
    protected static string $resource = StateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
