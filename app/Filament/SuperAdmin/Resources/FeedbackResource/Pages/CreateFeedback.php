<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\FeedbackResource\Pages;

use App\Filament\SuperAdmin\Resources\FeedbackResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeedback extends CreateRecord
{
    protected static string $resource = FeedbackResource::class;
}
