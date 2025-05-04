<?php

namespace App\Filament\Widgets;

use App\Models\GymMember;
use App\Models\MemberPayment;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;

class RecentActivity extends BaseWidget
{
    protected static ?string $heading = 'Recent Activity';

    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()->hasRole('gym_owner');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->whereIn('subject_type', [
                        GymMember::class,
                        MemberPayment::class,
                    ])
                    ->whereHasMorph('subject', GymMember::class, function ($query) {
                        $query->whereHas('gym', fn ($query) => $query->where('owner_id', auth()->user()->id));
                    })
                    ->orWhereHasMorph('subject', MemberPayment::class, function ($query) {
                        $query->whereHas('member.gym', fn ($query) => $query->where('owner_id', auth()->user()->id));
                    })
                    ->latest()
                    ->take(5)
            )
            ->columns([
                IconColumn::make('event')
                    ->label('')
                    ->icon(fn (string $state, Activity $record): string => match ($state) {
                        'created' => match ($record->subject_type) {
                            GymMember::class => 'heroicon-m-user-plus',
                            MemberPayment::class => 'heroicon-m-currency-rupee',
                            default => 'heroicon-m-information-circle',
                        },
                        'updated' => 'heroicon-m-check-circle',
                        'deleted' => 'heroicon-m-trash',
                        default => 'heroicon-m-information-circle',
                    })
                    ->color(fn (string $state, Activity $record): string => match ($state) {
                        'created' => match ($record->subject_type) {
                            GymMember::class => 'success',
                            MemberPayment::class => 'info',
                            default => 'gray',
                        },
                        'updated' => 'primary',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('description')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ]);
    }
}
