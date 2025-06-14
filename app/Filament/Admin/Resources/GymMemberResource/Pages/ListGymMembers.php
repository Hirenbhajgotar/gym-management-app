<?php

namespace App\Filament\Admin\Resources\GymMemberResource\Pages;

use App\Filament\Admin\Resources\GymMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGymMembers extends ListRecords
{
    protected static string $resource = GymMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
