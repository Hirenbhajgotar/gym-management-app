<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\GymMemberResource;
use App\Jobs\SendMembershipExpiryNotificationJob;
use App\Models\GymMember;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MembershipExpiryAlerts extends BaseWidget
{
    protected static ?string $heading = 'Membership Expiry Alerts';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => 'refreshWidget',
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
                    ->getStateUsing(fn ($record) => $record->membership_end < now() ? 'Expired' : 'Expiring Soon')
                    ->colors([
                        'danger' => 'Expired',
                        'warning' => 'Expiring Soon',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->url(fn (GymMember $record): string => GymMemberResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('sendAlert')
                    ->label('Send Alert')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->action(function (GymMember $record) {
                        $daysLeft = now()->diffInDays($record->membership_end, false);

                        SendMembershipExpiryNotificationJob::dispatch($record, (int) $daysLeft);

                        Notification::make()
                            ->title('Alert sent successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('viewAllMembers')
                    ->label('View All Members')
                    ->color('success')
                    ->outlined()
                    ->url(fn (): string => GymMemberResource::getUrl()),
            ]);
    }
}
