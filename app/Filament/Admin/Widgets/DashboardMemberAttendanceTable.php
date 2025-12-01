<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\GymMemberResource;
use App\Models\Attendances;
use App\Models\GymMember;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DashboardMemberAttendanceTable extends BaseWidget
{
    protected static ?string $heading = 'Today\'s Member Attendance';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '900s';

    protected static ?int $sort = 7;

    public function table(Table $table): Table
    {
        /**
         * @var \App\Models\Gym $tenant
         */
        $tenant = Filament::getTenant();
        $today = Carbon::today();
        $currentGymId = $tenant->id;

        return $table
            ->query(
                Attendances::query()
                    ->where('attendanceable_type', GymMember::class)
                    ->where('gym_id', $currentGymId)
                    ->whereDate('check_in_time', $today)
                    ->with('attendanceable')
                // ->take(100)
            )
            ->defaultPaginationPageOption(10)
            ->defaultSort('status', 'desc')
            ->columns([
                TextColumn::make('attendanceable.name')
                    ->label('Member Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('check_in_time')
                    ->label('Check-In Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('check_out_time')
                    ->label('Check-Out Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(function ($record) {
                        return $record->durationInMinutes . ' mins';
                    })
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->url(fn (Attendances $record): string => GymMemberResource::getUrl('edit', ['record' => $record->attendanceable_id])),
            ]);
    }
}
