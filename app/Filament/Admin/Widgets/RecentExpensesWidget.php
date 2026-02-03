<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Expense;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Dashboard widget showing recent expenses for quick review.
 */
class RecentExpensesWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Expenses';

    public function table(Table $table): Table
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return $table
            ->query(
                Expense::query()
                    ->where('gym_id', $gym?->id)
                    ->latest('expense_date')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d M')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color ?? 'gray'),

                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency ?? 'INR')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('phosphor-eye')
                    ->url(fn ($record) => route('filament.admin.resources.expenses.view', [
                        'tenant' => $gym->slug,
                        'record' => $record->id,
                    ])),
            ])
            ->paginated(false);
    }
}
