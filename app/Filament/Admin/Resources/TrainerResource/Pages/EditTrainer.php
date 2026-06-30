<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrainerResource\Pages;

use App\Filament\Admin\Resources\TrainerResource;
use Filament\Resources\Pages\EditRecord;

class EditTrainer extends EditRecord
{
    protected static string $resource = TrainerResource::class;

    protected function getRedirectUrl(): string
    {
        return TrainerResource::getUrl('index');
    }
}
