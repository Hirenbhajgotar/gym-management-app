<?php

namespace App\Filament\Widgets;

use App\Models\GymMember;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TodaysBirthdays extends BaseWidget
{
    protected static ?string $heading = 'Today\'s Birthdays';

    protected static ?int $sort = 3;

    // protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        // Replace with your actual query to fetch members with birthdays today
        return GymMember::query()
            ->whereHas('gym', fn ($query) => $query->where('owner_id', auth()->user()->id))
            ->whereMonth('dob', now()->month)
            ->whereDay('dob', now()->day)
            ->orderBy('name', 'asc');
    }

    protected function getTableColumns(): array
    {
        // Define your table columns here (example)
        return [
            \Filament\Tables\Columns\TextColumn::make('name')->label('Name'),
            \Filament\Tables\Columns\TextColumn::make('birth_date')->label('Birthday'),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No birthdays today.';
    }
}
