<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Filament\SuperAdmin\Resources\LoginActivityResource\Widgets\LoginActivityStatsWidget;
use App\Models\Gym;
use App\Services\LoginActivityService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Carbon;

class LoginActivity extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

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

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\UserLoginLog::query()
            ->with(['user', 'gym'])
            ->orderByDesc('login_at');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('user.name')
                ->label('User')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('user.email')
                ->label('Email')
                ->searchable()
                ->toggleable(),
            Tables\Columns\TextColumn::make('gym.name')
                ->label('Gym')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->getStateUsing(fn ($record) => $record?->isCurrentlyLoggedIn() ? 'Logged In' : 'Logged Out')
                ->badge()
                ->color(fn (string $state): string => $state === 'Logged In' ? 'success' : 'gray'),
            Tables\Columns\TextColumn::make('session_duration')
                ->label('Duration')
                ->getStateUsing(fn ($record) => $record?->session_duration)
                ->visible(fn ($record) => ! $record?->isCurrentlyLoggedIn()),
            Tables\Columns\TextColumn::make('login_at')
                ->label('Login Time')
                ->dateTime('M j, Y g:i A')
                ->sortable(),
            Tables\Columns\TextColumn::make('logged_out_at')
                ->label('Logout Time')
                ->dateTime('M j, Y g:i A')
                ->visible(fn ($record) => $record?->logged_out_at !== null)
                ->toggleable(),
            Tables\Columns\TextColumn::make('ip_address')
                ->label('IP Address')
                ->toggleable(),
            Tables\Columns\TextColumn::make('device_type')
                ->label('Device')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'desktop' => 'info',
                    'mobile' => 'success',
                    'tablet' => 'warning',
                    'bot' => 'danger',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('login_method')
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
                    \Filament\Forms\Components\Select::make('role')
                        ->options([
                            'gym_owner' => 'Gym Owner',
                            'manager' => 'Manager',
                            'receptionist' => 'Receptionist',
                            'trainer' => 'Trainer',
                        ]),
                ])
                ->query(function ($query, array $data): \Illuminate\Database\Eloquent\Builder {
                    return $query->when(
                        $data['role'] ?? null,
                        function ($query, $role) {
                            $userIds = app(LoginActivityService::class)->getUserIdsByRoles([$role]);

                            return $query->whereIn('user_id', $userIds);
                        }
                    );
                }),

            SelectFilter::make('gym_id')
                ->label('Gym')
                ->options(fn () => Gym::pluck('name', 'id')),

            Filter::make('date_from')
                ->label('From Date')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('date_from')
                        ->label('From Date'),
                ])
                ->query(function ($query, array $data): \Illuminate\Database\Eloquent\Builder {
                    return $query->when(
                        $data['date_from'] ?? null,
                        fn ($query, $date) => $query->where('login_at', '>=', Carbon::parse($date)->startOfDay())
                    );
                }),

            Filter::make('date_to')
                ->label('To Date')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('date_to')
                        ->label('To Date'),
                ])
                ->query(function ($query, array $data): \Illuminate\Database\Eloquent\Builder {
                    return $query->when(
                        $data['date_to'] ?? null,
                        fn ($query, $date) => $query->where('login_at', '<=', Carbon::parse($date)->endOfDay())
                    );
                }),
        ];
    }

    protected function getTableFiltersLayout(): FiltersLayout
    {
        return FiltersLayout::AboveContent;
    }
}
