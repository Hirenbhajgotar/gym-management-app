<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages;

use App\Enums\AttendanceStatus;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListGymMemberAttendances extends ListRecords
{
    protected static string $resource = GymMemberAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return GymMemberAttendanceResource::getWidgets();
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make('All'),
            'Present' => Tab::make()->query(fn($query) => $query->active()->where('status', AttendanceStatus::Present->value)),
            'Completed' => Tab::make()->query(fn($query) => $query->completed()->where('status', AttendanceStatus::Completed->value)),
            // 'Absent' => Tab::make()->query(fn($query) => $query->absent()->where('status', AttendanceStatus::Absent->value)),
        ];
    }
}
