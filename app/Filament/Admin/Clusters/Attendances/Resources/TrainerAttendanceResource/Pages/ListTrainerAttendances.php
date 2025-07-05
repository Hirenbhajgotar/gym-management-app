<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages;

use App\Enums\AttendanceStatus;
use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListTrainerAttendances extends ListRecords
{
    protected static string $resource = TrainerAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return TrainerAttendanceResource::getWidgets();
    }

    // public function getTabs(): array
    // {
    //     return [
    //         null => Tab::make('All'),
    //         'Present' => Tab::make()->query(fn($query) => $query->active()->where('status', AttendanceStatus::Present->value)),
    //         'Completed' => Tab::make()->query(fn($query) => $query->completed()->where('status', AttendanceStatus::Completed->value)),
    //         'Absent' => Tab::make()->query(fn($query) => $query->absent()->where('status', AttendanceStatus::Absent->value)),
    //     ];
    // }
}
