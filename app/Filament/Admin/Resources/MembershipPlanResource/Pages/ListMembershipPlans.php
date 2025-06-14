<?php

namespace App\Filament\Admin\Resources\MembershipPlanResource\Pages;

use App\Filament\Admin\Resources\MembershipPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMembershipPlans extends ListRecords
{
    protected static string $resource = MembershipPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
