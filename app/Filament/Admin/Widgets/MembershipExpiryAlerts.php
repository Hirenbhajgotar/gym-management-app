<?php

namespace App\Filament\Admin\Widgets;

use App\Models\GymMember;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MembershipExpiryAlerts extends BaseWidget
{
    protected static ?string $heading = 'Membership Expiry Alerts';

    protected static ?string $pollingInterval = '900s';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()->hasRole('gym_owner');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GymMember::query()
                    ->whereHas('gym', fn($query) => $query->where('owner_id', auth()->user()->id))
                    ->where('membership_end', '<=', now()->addDays(15)) // Expired or expiring within 15 days
                    ->orderBy('membership_end', 'asc')
                    ->take(5)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('gym.name')
                    ->label('Gym')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('membership_end')
                    ->label('Expiry Date')
                    ->date('M d, Y')
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn($record) => $record->membership_end < now() ? 'Expired' : 'Expiring Soon')
                    ->colors([
                        'danger' => 'Expired',
                        'warning' => 'Expiring Soon',
                    ]),
            ]);
    }
}
