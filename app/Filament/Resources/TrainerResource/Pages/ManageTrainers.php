<?php

namespace App\Filament\Resources\TrainerResource\Pages;

use App\Filament\Exports\TrainerExporter;
use App\Filament\Imports\TrainerImporter;
use App\Filament\Resources\TrainerResource;
use App\Imports\TrainerImport;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction;
use Filament\Notifications\Notification;
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
                ->exporter(TrainerExporter::class)
                ->formats([ExportFormat::Xlsx]),
            // ->columnMapping(false),

            ExcelImportAction::make()
                ->uploadField(
                    fn($upload) => $upload
                        ->label("Import Trainers")
                )
                ->use(TrainerImport::class)
                ->mutateBeforeValidationUsing(function (array $data): array {
                    // Clean phone number before validation
                    if (isset($data['phone'])) {
                        $data['phone'] = preg_replace('/[^0-9]/', '', $data['phone']);
                    }
                    return $data;
                })
                ->sampleFileExcel(
                    url: url('import/templates/xls/trainers-sample.xlsx'),
                    sampleButtonLabel: 'Download Sample Excel'
                ),

            // ImportAction::make()
            //     ->importer(TrainerImporter::class),

            Actions\CreateAction::make(),
        ];
    }

    protected function paginateTableQuery(Builder $query): Paginator
    {
        return $query->simplePaginate(($this->getTableRecordsPerPage() === 'all') ? $query->count() : $this->getTableRecordsPerPage());
    }
}
