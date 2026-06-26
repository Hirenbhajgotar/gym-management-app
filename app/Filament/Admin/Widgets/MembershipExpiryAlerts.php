<?php

namespace App\Filament\Admin\Widgets;

use Filament\Tables\Actions\Action;
use App\Models\Gym;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\BaseWidgets\BaseTableWidget;
use App\Jobs\SendMembershipExpiryNotificationJob;
use App\Models\GymMember;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembershipExpiryAlerts extends BaseTableWidget
{
    protected static ?string $heading = 'Membership Expiry Alerts';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['widget'],
            DefaultRole::RECEPTIONIST->value => ['widget'],
            DefaultRole::TRAINER->value => [],
        ];
    }

    protected function getListeners(): array
    {
        /** @var Gym|null $tenant */
        $tenant = Filament::getTenant();
        $gymId = $tenant?->id;

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
                Action::make('open')
                    ->url(fn (GymMember $record): string => GymMemberResource::getUrl('edit', ['record' => $record])),
                Action::make('sendAlert')
                    ->label('Send Alert')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->action(function (GymMember $record) {
                        $daysLeft = now()->diffInDays($record->membership_end, false);

                        dispatch(new SendMembershipExpiryNotificationJob($record, (int) $daysLeft));

                        Notification::make()
                            ->title('Alert sent successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
            ])
            ->headerActions([
                Action::make('viewAllMembers')
                    ->label('View All Members')
                    ->color('success')
                    ->outlined()
                    ->url(fn (): string => GymMemberResource::getUrl()),
            ]);
    }
}
