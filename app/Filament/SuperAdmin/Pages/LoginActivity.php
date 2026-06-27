<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Pages;

use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use App\Models\UserLoginLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\Date;
use App\Filament\BasePages\BasePage;
use App\Filament\SuperAdmin\Resources\LoginActivityResource\Widgets\LoginActivityStatsWidget;
use App\Models\Gym;
use App\Services\LoginActivityService;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

class LoginActivity extends BasePage implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Login Activity';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?string $title = 'Staff Login Activity';

    protected ?string $heading = 'Login Activity';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.super-admin.pages.login-activity';

    protected function getHeaderWidgets(): array
    {
        return [
            LoginActivityStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }

    protected function getTableQuery(): Builder
    {
        return UserLoginLog::query()
            ->with(['user', 'gym'])
            ->orderByDesc('login_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('user.name')
                ->label('User')
                ->searchable()
                ->sortable(),
            TextColumn::make('user.email')
                ->label('Email')
                ->searchable()
                ->toggleable(),
            TextColumn::make('gym.name')
                ->label('Gym')
                ->searchable()
                ->sortable(),
            TextColumn::make('status')
                ->label('Status')
                ->getStateUsing(fn ($record) => $record?->isCurrentlyLoggedIn() ? 'Logged In' : 'Logged Out')
                ->badge()
                ->color(fn (string $state): string => $state === 'Logged In' ? 'success' : 'gray'),
            TextColumn::make('session_duration')
                ->label('Duration')
                ->getStateUsing(fn ($record) => $record?->session_duration)
                ->visible(fn ($record) => ! $record?->isCurrentlyLoggedIn()),
            TextColumn::make('login_at')
                ->label('Login Time')
                ->dateTime('M j, Y g:i A')
                ->sortable(),
            TextColumn::make('logged_out_at')
                ->label('Logout Time')
                ->dateTime('M j, Y g:i A')
                ->visible(fn ($record) => $record?->logged_out_at !== null)
                ->toggleable(),
            TextColumn::make('ip_address')
                ->label('IP Address')
                ->toggleable(),
            TextColumn::make('device_type')
                ->label('Device')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'desktop' => 'info',
                    'mobile' => 'success',
                    'tablet' => 'warning',
                    'bot' => 'danger',
                    default => 'gray',
                }),
            TextColumn::make('login_method')
                ->label('Method')
                ->toggleable(),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('role')
                ->label('Role')
                ->form([
                    Select::make('role')
                        ->options([
                            'gym_owner' => 'Gym Owner',
                            'manager' => 'Manager',
                            'receptionist' => 'Receptionist',
                            'trainer' => 'Trainer',
                        ]),
                ])
                ->query(function ($query, array $data): Builder {
                    return $query->when(
                        $data['role'] ?? null,
                        function ($query, $role) {
                            $userIds = resolve(LoginActivityService::class)->getUserIdsByRoles([$role]);

                            return $query->whereIn('user_id', $userIds);
                        }
                    );
                }),

            SelectFilter::make('gym_id')
                ->label('Gym')
                ->options(fn () => Gym::query()->pluck('name', 'id')),

            Filter::make('date_from')
                ->label('From Date')
                ->form([
                    DatePicker::make('date_from')
                        ->label('From Date'),
                ])
                ->query(function ($query, array $data): Builder {
                    return $query->when(
                        $data['date_from'] ?? null,
                        fn ($query, $date) => $query->where('login_at', '>=', Date::parse($date)->startOfDay())
                    );
                }),

            Filter::make('date_to')
                ->label('To Date')
                ->form([
                    DatePicker::make('date_to')
                        ->label('To Date'),
                ])
                ->query(function ($query, array $data): Builder {
                    return $query->when(
                        $data['date_to'] ?? null,
                        fn ($query, $date) => $query->where('login_at', '<=', Date::parse($date)->endOfDay())
                    );
                }),
        ];
    }

    protected function getTableFiltersLayout(): FiltersLayout
    {
        return FiltersLayout::AboveContent;
    }
}
