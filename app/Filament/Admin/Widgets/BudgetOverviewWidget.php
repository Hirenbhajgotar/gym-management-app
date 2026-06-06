<?php

namespace App\Filament\Admin\Widgets;

use App\Contracts\HasFilamentPermissions;
use App\Models\ExpenseBudget;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Dashboard widget showing budget usage overview with progress bars.
 */
class BudgetOverviewWidget extends Widget implements HasFilamentPermissions
{
    protected static string $view = 'filament.widgets.budget-overview';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.ExpenseStatsUpdated" => '$refresh',
        ];
    }

    public function getBudgets()
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        if (! $gym) {
            return collect();
        }

        return ExpenseBudget::query()
            ->where('gym_id', $gym->id)
            ->active()
            ->current()
            ->with('category')
            ->get()
            ->map(function ($budget) {
                return [
                    'id' => $budget->id,
                    'name' => $budget->name,
                    'category' => $budget->category?->name ?? 'All Categories',
                    'amount' => $budget->amount,
                    'currency' => $budget->currency,
                    'spent' => $budget->getSpentAmount(),
                    'remaining' => $budget->getRemainingAmount(),
                    'percentage' => $budget->getUsagePercentage(),
                    'color' => $budget->getStatusColor(),
                    'period' => ucfirst($budget->period),
                    'end_date' => $budget->end_date->format('d M Y'),
                ];
            });
    }
}
