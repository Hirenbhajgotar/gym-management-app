<?php

namespace App\Filament\Admin\Resources\LeadResource\Pages;

use App\Filament\Admin\Pages\LeadKanbanBoard;
use App\Filament\Admin\Resources\LeadResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    protected function getRedirectUrl(): string
    {
        return LeadKanbanBoard::getUrl();
    }
}
