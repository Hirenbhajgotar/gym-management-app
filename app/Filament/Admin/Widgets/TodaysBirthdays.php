<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\DefaultRole;
use App\Filament\BaseWidgets\BaseTableWidget;
use App\Models\Gym;
use App\Models\GymMember;
use App\Notifications\BirthdayNotification;
use Carbon\Carbon;
use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TodaysBirthdays extends BaseTableWidget
{
    protected static ?string $heading = 'Today\'s Birthdays';

    protected static ?int $sort = 5;

    protected static ?string $pollingInterval = null;

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
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::GYM_OWNER->value)
            || parent::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GymMember::query()
                    ->with('gym')
                    ->whereHas('gym')
                    ->birthdayToday()
                    ->orderBy('name', 'asc')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->description(function (GymMember $record) {
                        /** @var Carbon $dob */
                        $dob = $record->dob;

                        return $dob->format('F j, Y');
                    }),
                TextColumn::make('dob')
                    ->label('Age')
                    ->getStateUsing(function (GymMember $record) {
                        /** @var Carbon $dob */
                        $dob = $record->dob;

                        return $dob->age . ' Years Old';
                    }),
            ])
            ->actions([
                Action::make('wish')
                    ->label('Wish Happy Birthday')
                    ->icon('phosphor-gift')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalIcon('phosphor-gift')
                    ->modalDescription(function (GymMember $record): string {
                        $cacheKey = BirthdayNotification::birthdayCacheKey($record->id);
                        $message = "Send Birthday Wish to {$record->name}";

                        return Cache::has($cacheKey) ? 'Birthday wish already sent' : $message;
                    })
                    ->action(function (array $data, GymMember $record) {
                        $cacheKey = BirthdayNotification::birthdayCacheKey($record->id);
                        $forceSend = (bool) ($data['force_send'] ?? false);

                        if (Cache::has($cacheKey) && ! $forceSend) {
                            Notification::make()
                                ->title('Birthday wish already sent')
                                ->warning()
                                ->send();

                            return;
                        }

                        try {
                            $record->notify(new BirthdayNotification($record));

                            Cache::put($cacheKey, true, now()->endOfDay());
                        } catch (Exception $e) {
                            Log::error('Failed to send birthday notification', [
                                'member_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Failed to send birthday wish')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Birthday wish sent successfully')
                            ->success()
                            ->send();
                    })
                    ->form([
                        Checkbox::make('force_send')
                            ->label('Force send')
                            ->default(false)
                            ->visible(fn (GymMember $record): bool => Cache::has(BirthdayNotification::birthdayCacheKey($record->id))),
                    ]),
            ])
            ->emptyStateHeading('No birthdays today.');
    }
}
