<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\GymMember;
use App\Models\MemberPayment;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class RecentActivity extends BaseWidget
{
    protected static ?string $heading = 'Recent Activity';

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1000;

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => 'refreshWidget',
            "echo-private:reports.{$gymId},.RevenueStatsUpdated" => 'refreshWidget',
        ];
    }

    public function refreshWidget(): void
    {
        $this->resetTable();
    }

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
                    ->whereHasMorph('subject', GymMember::class, function (Builder $query) {
                        $query->whereHas('gym');
                    })
                    ->orWhereHasMorph('subject', MemberPayment::class, function ($query) {
                        $query->whereHas('member.gym');
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
