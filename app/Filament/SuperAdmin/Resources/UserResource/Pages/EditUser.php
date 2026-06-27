<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\UserResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\SuperAdmin\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
