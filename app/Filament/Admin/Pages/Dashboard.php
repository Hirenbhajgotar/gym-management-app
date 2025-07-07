<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Widgets\MemberAttendanceStats;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        // Generate month-year options (last 12 months)
        $monthYearOptions = [];
        $currentDate = Carbon::now();
        for ($i = 0; $i < 12; $i++) {
            $date = $currentDate->copy()->subMonths($i);
            $monthYearOptions[$date->format('Y-m')] = $date->format('F Y'); // e.g., "May 2025"
        }

        return $form
            ->schema([
                Section::make()
                    ->schema([
                        // Select::make('gymId')
                        //     ->label('Select Gym')
                        //     ->options(function () {
                        //         return auth()->user()->gyms()->get()->pluck('name', 'id');
                        //     }),
                        // DatePicker::make('startDate', null),
                        // DatePicker::make('endDate', null),
                        Select::make('monthYear')
                            ->label('Select Month')
                            ->options($monthYearOptions)
                            ->default($currentDate->format('Y-m')) // Default to current month
                            ->reactive(),
                    ])
                    ->columns(2),
            ])
            ->statePath('filters');
    }
}
