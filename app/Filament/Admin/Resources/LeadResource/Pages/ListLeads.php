<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LeadResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\LeadResource;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('Add Lead'),
        ];
    }
}
