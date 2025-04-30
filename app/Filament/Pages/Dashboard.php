<?php

namespace App\Filament\Pages;

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
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('gymId')
                            ->label('Select Gym')
                            ->options(function () {
                                return auth()->user()->gyms()->pluck('name', 'id');
                            }),
                        DatePicker::make('startDate', Carbon::now()->startOfMonth())
                            ->native(false),
                        DatePicker::make('endDate', Carbon::now()->endOfMonth())
                            ->native(false),
                    ])
                    ->columns(3),
            ]);
    }
}
