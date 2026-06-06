<?php

namespace App\Filament\Admin\Widgets;

use App\Contracts\HasFilamentPermissions;
use App\Enums\DefaultRole;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Dashboard widget showing expenses pending approval for managers/owners.
 */
class PendingExpenseApprovalsWidget extends BaseWidget implements HasFilamentPermissions
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Pending Expense Approvals';

    protected static ?string $pollingInterval = null;

    public static function canView(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->hasAnyRole([
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
        ]);
    }

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.ExpenseStatsUpdated" => '$refresh',
        ];
    }

    public function table(Table $table): Table
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $table
            ->query(
                Expense::query()
                    ->where('gym_id', $gym?->id)
                    ->where('status', ExpenseStatus::PENDING)
                    ->latest('submitted_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('By')
                    ->limit(15),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color ?? 'gray'),

                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency ?? 'INR')
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('phosphor-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $user->can('approve', $record))
                    ->action(fn ($record) => $record->approve($user)),

                Tables\Actions\Action::make('view')
                    ->icon('phosphor-eye')
                    ->url(fn ($record) => route('filament.admin.resources.expenses.view', [
                        'tenant' => $gym->slug,
                        'record' => $record->id,
                    ])),
            ])
            ->paginated(false)
            ->emptyStateHeading('No pending approvals')
            ->emptyStateDescription('All expenses have been reviewed.')
            ->emptyStateIcon('phosphor-check-circle');
    }
}
