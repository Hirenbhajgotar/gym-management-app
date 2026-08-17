<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\Exports\GymMemberExporter;
use App\Filament\Imports\GymMemberImporter;
use App\Imports\GymMemberImport;
use App\Models\GymMember;
use App\Models\User;
use App\Traits\HasTenantAccess;
use Filament\Actions\CreateAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction as ActionsImportAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListGymMembers extends ListRecords
{
    use HasTenantAccess;

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
                )
                ->visible(config()->boolean('app.custom_import_action')),

            ActionsImportAction::make()
                ->importer(GymMemberImporter::class)
                ->label('Import Members')
                ->icon('heroicon-o-arrow-down-tray')
                ->options([
                    'tenantId' => $this->getGymId(),
                ])
                ->visible(! config()->boolean('app.custom_import_action')),

            CreateAction::make()
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

    public function getUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->membershipActive();
                    $query->notArchived();
                }),
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->membershipExpired();
                    $query->notArchived();
                }),
            'archived' => Tab::make('Archived')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->archived();
                }),
        ];
    }
}
