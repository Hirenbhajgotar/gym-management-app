<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\DefaultRole;
use App\Filament\BaseWidgets\BaseTableWidget;
use App\Models\Expense;
use App\Models\Gym;
use Filament\Facades\Filament;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Dashboard widget showing recent expenses for quick review.
 */
class RecentExpensesWidget extends BaseTableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Expenses';

    protected static ?string $pollingInterval = null;

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
            "echo-private:reports.{$gymId},.ExpenseStatsUpdated" => '$refresh',
        ];
    }

    public function table(Table $table): Table
    {
        /** @var Gym|null $gym */
        $gym = Filament::getTenant();

        return $table
            ->query(
                Expense::query()
                    ->where('gym_id', $gym?->id)
                    ->latest('expense_date')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d M')
                    ->sortable(),

                TextColumn::make('title')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category->color ?? 'gray'),

                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency ?? 'INR')
                    ->alignEnd(),

                TextColumn::make('status')
                    ->badge(),
            ])
            ->actions([
                Action::make('view')
                    ->icon('phosphor-eye')
                    ->url(fn ($record) => route('filament.admin.resources.expenses.view', [
                        'tenant' => $gym->slug,
                        'record' => $record->id,
                    ])),
            ])
            ->paginated(false);
    }
}
