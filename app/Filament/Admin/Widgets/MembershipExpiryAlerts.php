<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\BaseWidgets\BaseTableWidget;
use App\Jobs\SendMembershipExpiryNotificationJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppAccount;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class MembershipExpiryAlerts extends BaseTableWidget
{
    protected static ?string $heading = 'Membership Expiry Alerts';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->hasRole(DefaultRole::GYM_OWNER->value);
    }

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

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GymMember::query()
                    ->with(['gym'])
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
                    ->color('success')
                    ->action(function (array $data, GymMember $record) {
                        $cacheKey = "manual_expiry_alert_{$record->id}_" . today()->toDateString();
                        $forceSend = $data['force_send'] ?? false;

                        if (! Cache::add($cacheKey, true, now()->addDay()) && ! $forceSend) {
                            Notification::make()
                                ->title('Alert already sent today')
                                ->warning()
                                ->send();

                            return;
                        }

                        $daysLeft = now()->diffInDays($record->membership_end, false);
                        dispatch(new SendMembershipExpiryNotificationJob($record, (int) $daysLeft));

                        Notification::make()->title('Alert sent successfully')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-chat-bubble-oval-left')
                    ->modalDescription(function (GymMember $record): string {
                        $whatsappAccount = WhatsAppAccount::query()->where('gym_id', Filament::getTenant()?->getKey())->first();
                        if (! $whatsappAccount || ! $whatsappAccount->isActive()) {
                            return "Whatsapp Integration is not enabled. Send Email Notification to {$record->name}";
                        }

                        $cacheKey = "manual_expiry_alert_{$record->id}_" . today()->toDateString();
                        $message = "Send Whatsapp Alert Notification to {$record->name}";

                        return Cache::has($cacheKey) ? 'Alert already sent today' : $message;
                    })
                    ->form([
                        Checkbox::make('force_send')
                            ->label('Force send')
                            ->default(false)
                            ->visible(fn (GymMember $record): bool => Cache::has("manual_expiry_alert_{$record->id}_" . today()->toDateString())),
                    ]),
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
