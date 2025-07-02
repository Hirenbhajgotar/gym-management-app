<?php

namespace App\Filament\SuperAdmin\Resources\FeedbackResource\Pages;

use App\Filament\SuperAdmin\Resources\FeedbackResource;
use App\Filament\SuperAdmin\Resources\FeedbackResource as ResourcesFeedbackResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewFeedback extends ViewRecord
{
    protected static string $resource = FeedbackResource::class;

    protected function getActions(): array
    {
        return [
            // Add custom actions if needed
        ];
    }
}
