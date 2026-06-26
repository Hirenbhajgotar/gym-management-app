<?php

namespace App\Filament\SuperAdmin\Resources\FeedbackResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\SuperAdmin\Resources\FeedbackResource;
use Filament\Resources\Pages\ListRecords;

class ListFeedback extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
