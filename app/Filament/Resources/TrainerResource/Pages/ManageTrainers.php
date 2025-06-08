<?php

namespace App\Filament\Resources\TrainerResource\Pages;

use App\Filament\Exports\TrainerExporter;
use App\Filament\Resources\TrainerResource;
use App\Imports\TrainerImport;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
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
                ->beforeImport(function () {
                    $recipient = auth()->user();

                    if ($recipient) {
                        Notification::make()
                            ->info()
                            ->title('Import Started')
                            ->body('Your import is being processed in the background. You will be notified once it is complete.')
                            ->send();
                    }
                })
                ->sampleExcel(
                    sampleData: [
                        [
                            'gym' => 'Fitness Hub',
                            'name' => 'John Doe',
                            'email' => 'john.doe@example.com',
                            'phone' => '9876543210',
                            'specialization' => 'Strength Training',
                            'experience_years' => 5,
                            'is_available' => 'Yes',
                            'available_hours' => '9 AM - 5 PM',
                            'basic_salary' => 50000,
                            'pt_salary_per_member' => 1000,
                        ],
                    ],
                    fileName: 'trainers-sample.xlsx',
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
