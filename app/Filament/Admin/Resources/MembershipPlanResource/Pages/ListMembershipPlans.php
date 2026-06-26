<?php

namespace App\Filament\Admin\Resources\MembershipPlanResource\Pages;

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\CreateAction;
use App\Models\User;
use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Admin\Resources\MembershipPlanResource;
use App\Filament\Admin\Resources\MembershipPlanResource\Exports\MembershipPlanExporter;
use App\Imports\MembershipPlansImport;
use App\Models\MembershipPlan;
use Filament\Resources\Pages\ListRecords;

class ListMembershipPlans extends ListRecords
{
    protected static string $resource = MembershipPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Export Plans')
                ->exporter(MembershipPlanExporter::class)
                ->formats([ExportFormat::Xlsx]),

            ImportAction::make()
                ->label('Import Plans')
                ->modalHeading('Import Plans')
                ->importer(MembershipPlansImport::class)
                ->use(MembershipPlan::class)
                ->sampleFile(
                    urlOrData: [
                        [
                            'name' => 'Gold',
                            'price' => '100',
                            'duration_in_months' => '12',
                            'description' => 'Full access',
                        ],
                        [
                            'name' => 'Silver',
                            'price' => '50',
                            'duration_in_months' => '6',
                            'description' => 'Limited access',
                        ],
                    ],
                    fileName: 'plans-sample.xlsx',
                    sampleButtonLabel: 'Download Sample File',
                ),

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
        $user = auth()->user();

        return $user;
    }
}
