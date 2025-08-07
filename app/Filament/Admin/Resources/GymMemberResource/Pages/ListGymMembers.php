<?php

namespace App\Filament\Admin\Resources\GymMemberResource\Pages;

use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\Exports\GymMemberExporter;
use Filament\Actions;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ListRecords;

class ListGymMembers extends ListRecords
{
    protected static string $resource = GymMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Export action
            Actions\ExportAction::make()
                ->label('Export Members')
                ->icon('heroicon-o-arrow-up-tray')
                ->exporter(GymMemberExporter::class)
                ->formats([ExportFormat::Xlsx]),

            // Create action
            Actions\CreateAction::make()->label('Add Member')
                ->icon('heroicon-o-plus-circle')
                ->color('primary'),
        ];
    }
}
