<?php

namespace App\Filament\Admin\Widgets;

use App\Models\GymMember;
use App\Notifications\BirthdayNotification;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Cache;

class TodaysBirthdays extends BaseWidget
{
    protected static ?string $heading = 'Today\'s Birthdays';

    protected static ?int $sort = 5;

    protected static ?string $pollingInterval = null;

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
                    ->whereHas('gym')
                    ->whereMonth('dob', now()->month)
                    ->whereDay('dob', now()->day)
                    ->orderBy('name', 'asc')
            )
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->description(fn (GymMember $record) => $record->dob->format('F j, Y')),
                \Filament\Tables\Columns\TextColumn::make('dob')
                    ->label('Age')
                    ->getStateUsing(fn (GymMember $record) => $record->dob->age . ' Years Old'),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('wish')
                    ->label('Wish Happy Birthday')
                    ->icon('phosphor-gift')
                    ->color('success')
                    ->disabled(fn (GymMember $record) => Cache::has("birthday_sent_{$record->id}_" . now()->year))
                    ->action(function (GymMember $record) {
                        $record->notify(new BirthdayNotification($record));

                        Cache::put("birthday_sent_{$record->id}_" . now()->year, true, now()->endOfDay());

                        Notification::make()
                            ->title('Birthday wish sent successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No birthdays today.');
    }
}
