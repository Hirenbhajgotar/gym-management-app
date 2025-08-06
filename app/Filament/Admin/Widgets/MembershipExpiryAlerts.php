<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\GymMemberResource;
use App\Models\GymMember;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;

class MembershipExpiryAlerts extends BaseWidget
{
    protected static ?string $heading = 'Membership Expiry Alerts';

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = '900s';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()->hasRole('gym_owner');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GymMember::query()
                    ->where('membership_end', '<=', now()->addDays(15)) // Expired or expiring within 15 days
                    ->orderBy('membership_end', 'asc')
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
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn($record) => $record->membership_end < now() ? 'Expired' : 'Expiring Soon')
                    ->colors([
                        'danger' => 'Expired',
                        'warning' => 'Expiring Soon',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->url(fn(GymMember $record): string => GymMemberResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([
                Tables\Actions\Action::make('viewAllMembers')
                    ->label('View All Members')
                    ->color('success')
                    ->outlined()
                    ->url(fn(): string => GymMemberResource::getUrl()),
            ]);
    }
}
