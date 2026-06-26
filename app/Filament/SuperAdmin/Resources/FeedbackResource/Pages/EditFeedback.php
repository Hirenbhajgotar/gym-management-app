<?php

namespace App\Filament\SuperAdmin\Resources\FeedbackResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\SuperAdmin\Resources\FeedbackResource;
use Filament\Resources\Pages\EditRecord;

class EditFeedback extends EditRecord
{
    protected static string $resource = FeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
