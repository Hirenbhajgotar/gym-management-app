<?php

namespace App\Filament\Admin\Resources\GymMemberResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\Exports\GymMemberExporter;
use App\Imports\GymMemberImport;
use App\Models\GymMember;
use Filament\Actions;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ListRecords;

class ListGymMembers extends ListRecords
{
    protected static string $resource = GymMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Export Members')
                ->exporter(GymMemberExporter::class)
                ->formats([ExportFormat::Xlsx]),

            ImportAction::make()
                ->label('Import Members')
                ->importer(GymMemberImport::class)
                ->use(GymMember::class)
                ->modalHeading('Import Members')
                ->sampleFile(
                    urlOrData: url('import/templates/xls/member-sample.xlsx'),
                    fileName: 'member-sample.xlsx',
                    sampleButtonLabel: 'Download Sample File'
                ),

            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->color('primary'),
        ];
    }

    public function getListeners(): array
    {
        return [
            "echo-private:App.Models.User.{$this->getUser()->id},.ImportCompleted" => '$refresh',
        ];
    }

    public function getUser(): \App\Models\User
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user;
    }
}
