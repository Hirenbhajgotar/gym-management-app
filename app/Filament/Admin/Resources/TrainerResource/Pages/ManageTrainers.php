<?php

namespace App\Filament\Admin\Resources\TrainerResource\Pages;

use App\Filament\Actions\Imports\ExcelImportAction;
use App\Filament\Admin\Resources\TrainerResource;
use App\Filament\Exports\TrainerExporter;
use App\Imports\TrainerImport;
use App\Models\Trainer;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class ManageTrainers extends ManageRecords
{
    protected static string $resource = TrainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Export Trainers')
                ->icon('heroicon-o-arrow-up-tray')
                ->exporter(TrainerExporter::class)
                ->formats([ExportFormat::Xlsx]),

            ExcelImportAction::make()
                ->label('Import Trainers')
                ->importer(TrainerImport::class)
                ->use(Trainer::class)
                ->modalHeading('Import Trainers')
                ->sampleFile(
                    url: url('import/templates/xls/trainers-sample.xlsx'),
                    sampleButtonLabel: 'Download Sample File'
                ),

            Actions\CreateAction::make(),
        ];
    }

    protected function paginateTableQuery(Builder $query): Paginator
    {
        return $query->simplePaginate(($this->getTableRecordsPerPage() === 'all') ? $query->count() : $this->getTableRecordsPerPage());
    }
}
