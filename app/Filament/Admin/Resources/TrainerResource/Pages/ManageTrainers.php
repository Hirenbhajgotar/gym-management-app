<?php

namespace App\Filament\Admin\Resources\TrainerResource\Pages;

use App\Filament\Actions\Imports\ExcelImportAction;
use App\Filament\Admin\Resources\TrainerResource;
use App\Filament\Exports\TrainerExporter;
use App\Imports\TrainerImport;
use App\Models\Trainer;
use EightyNine\ExcelImport\ExcelImportAction as ExcelImportExcelImportAction;
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

            // ExcelImportExcelImportAction::make()
            //     ->label('Import Trainers')
            //     ->color('gray')
            //     ->uploadField(
            //         fn($upload) => $upload
            //             ->label("Import Trainers")
            //     )
            //     ->use(TrainerImport::class)
            //     ->mutateBeforeValidationUsing(function (array $data): array {
            //         // Clean phone number before validation
            //         if (isset($data['phone'])) {
            //             $data['phone'] = preg_replace('/[^0-9]/', '', $data['phone']);
            //         }
            //         return $data;
            //     })
            //     ->sampleFileExcel(
            //         url: url('import/templates/xls/trainers-sample.xlsx'),
            //         sampleButtonLabel: 'Download Sample Excel'
            //     )
            //     // Use the additional form fields data
            //     ->beforeImport(function (array $data, $livewire, $excelImportAction) {
            //         // $excelImportAction->customImportData([
            //         //     'gym_id' => Filament::getTenant()?->id ?? null,
            //         // ]);
            //     }),

            ExcelImportAction::make()
                ->label('Import Trainers')
                ->importer(TrainerImport::class)
                ->use(Trainer::class)
                ->modalHeading('Import Trainers')
                ->modalSubmitActionLabel('Import Trainers')
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
