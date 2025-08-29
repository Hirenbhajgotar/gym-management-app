<?php

namespace App\Filament\Admin\Resources\MembershipPlanResource\Pages;

use App\Filament\Actions\Imports\ExcelImportAction;
use App\Filament\Admin\Resources\MembershipPlanResource;
use App\Imports\MembershipPlansImport;
use App\Models\MembershipPlan;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMembershipPlans extends ListRecords
{
    protected static string $resource = MembershipPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()
                ->label('Import Plans')
                ->modalHeading('Import Plans')
                ->importer(MembershipPlansImport::class)
                ->use(MembershipPlan::class)
                ->sampleFile(
                    urlOrData: [
                        ['name' => 'Gold', 'price' => '100', 'duration_in_months' => '12', 'description' => 'Full access'],
                        ['name' => 'Silver', 'price' => '50', 'duration_in_months' => '6', 'description' => 'Limited access'],
                    ],
                    fileName: 'plans-sample.xlsx',
                    sampleButtonLabel: 'Download Sample File'
                ),

            Actions\CreateAction::make(),
        ];
    }
}
