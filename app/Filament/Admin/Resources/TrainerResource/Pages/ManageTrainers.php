<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrainerResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Admin\Resources\TrainerResource;
use App\Filament\Exports\TrainerExporter;
use App\Imports\TrainerImport;
use App\Models\Trainer;
use App\Models\User;
use Filament\Actions\CreateAction;
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
                ->exporter(TrainerExporter::class)
                ->formats([ExportFormat::Xlsx]),

            ImportAction::make()
                ->label('Import Trainers')
                ->importer(TrainerImport::class)
                ->use(Trainer::class)
                ->modalHeading('Import Trainers')
                ->sampleFile(
                    urlOrData: url('import/templates/xls/trainers-sample.xlsx'),
                    sampleButtonLabel: 'Download Sample File'
                ),

            CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->color('primary'),
        ];
    }

    protected function paginateTableQuery(Builder $query): Paginator
    {
        return $query->simplePaginate(($this->getTableRecordsPerPage() === 'all') ? $query->count() : $this->getTableRecordsPerPage());
    }

    public function getListeners(): array
    {
        return [
            "echo-private:App.Models.User.{$this->getUser()->id},.ImportCompleted" => '$refresh',
        ];
    }

    public function getUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
