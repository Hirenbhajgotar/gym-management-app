<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\FeedbackResource\Pages;

use App\Filament\SuperAdmin\Resources\FeedbackResource;
use Filament\Resources\Pages\ViewRecord;

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
