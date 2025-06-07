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
use Filament\Forms\Components\TextInput;
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
                ->validateUsing([
                    'gym' => 'required|exists:gyms,name',
                    'name' => 'required|max:255',
                    'email' => 'required|email|unique:trainers,email|max:255',
                    'phone' => 'nullable|numeric',
                    'specialization' => 'nullable|string|max:255',
                    'experience_years' => 'nullable|integer|min:0',
                    'is_available' => 'nullable|in:Yes,No,0,1', // Accept 'Yes' or 'No'
                    'available_hours' => 'nullable|string|max:255',
                    'basic_salary' => 'nullable|numeric|min:0',
                    'pt_salary_per_member' => 'nullable|numeric|min:0',
                ])
                ->mutateBeforeValidationUsing(function (array $data): array {
                    // Clean phone number before validation
                    if (isset($data['phone'])) {
                        $data['phone'] = preg_replace('/[^0-9]/', '', $data['phone']);
                    }
                    // if (isset($data['is_available'])) {
                    //     // Normalize 'Yes'/'No' to boolean
                    //     $data['is_available'] = in_array(strtolower($data['is_available']), ['yes', '1'], true);
                    //     // $data['is_available'] = $data['is_available'] === 'yes';
                    // }
                    // dd($data);
                    return $data;
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
