<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\DefaultRole;
use App\Enums\ExpenseStatus;
use App\Filament\BaseWidgets\BaseTableWidget;
use App\Models\Expense;
use App\Models\Gym;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Dashboard widget showing expenses pending approval for managers/owners.
 */
class PendingExpenseApprovalsWidget extends BaseTableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Pending Expense Approvals';

    protected static ?string $pollingInterval = null;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['widget'],
            DefaultRole::RECEPTIONIST->value => [],
            DefaultRole::TRAINER->value => [],
        ];
    }

    public static function canView(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasAnyRole([
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
        ]);
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

        /** @var User $user */
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
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),

                TextColumn::make('title')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('user.name')
                    ->label('By')
                    ->limit(15),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category->color ?? 'gray'),

                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency ?? 'INR')
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->actions([
                Action::make('approve')
                    ->icon('phosphor-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $user->can('approve', $record))
                    ->action(fn ($record) => $record->approve($user)),

                Action::make('view')
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
