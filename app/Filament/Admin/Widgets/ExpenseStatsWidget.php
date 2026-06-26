<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\DefaultRole;
use App\Enums\ExpenseStatus;
use App\Filament\BaseWidgets\BaseStatsOverviewWidget;
use App\Helpers\Number as HelpersNumber;
use App\Models\Expense;
use App\Services\DashboardCacheService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard widget showing expense statistics (today, week, month, year).
 */
class ExpenseStatsWidget extends BaseStatsOverviewWidget
{
    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 2;

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
        /** @var \App\Models\Gym|null $tenant */
        $tenant = Filament::getTenant();
        $gymId = $tenant?->id;

        return [
            "echo-private:reports.{$gymId},.ExpenseStatsUpdated" => '$refresh',
        ];
    }

    protected function getStats(): array
    {
        /** @var \App\Models\Gym|null $gym */
        $gym = Filament::getTenant();

        if (! $gym) {
            return [];
        }

        return Cache::flexible(DashboardCacheService::EXPENSE_STATS . $gym->id, DashboardCacheService::getCacheTtl(), function () use ($gym) {
            // Only count approved and paid expenses
            $query = Expense::query()
                ->where('gym_id', $gym->id)
                ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID]);

            $today = $query->clone()->whereDate('expense_date', today())->sum('amount');

            $thisWeek = $query->clone()
                ->whereBetween('expense_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('amount');

            $thisMonth = $query->clone()
                ->whereYear('expense_date', now()->year)
                ->whereMonth('expense_date', now()->month)
                ->sum('amount');

            $thisYear = $query->clone()
                ->whereYear('expense_date', now()->year)
                ->sum('amount');

            // Pending approvals count
            $pendingCount = Expense::query()
                ->where('gym_id', $gym->id)
                ->where('status', ExpenseStatus::PENDING)
                ->count();

            $format = fn ($value) => HelpersNumber::abbreviateCurrency($value);

            return [
                Stat::make("Today's Expenses", $format($today))
                    ->icon('phosphor-money')
                    ->description('Expenses recorded today')
                    ->color('danger'),

                Stat::make("This Month's Expenses", $format($thisMonth))
                    ->icon('phosphor-calendar')
                    ->description('Expenses this month')
                    ->color('warning'),

                Stat::make("This Year's Expenses", $format($thisYear))
                    ->icon('phosphor-chart-line-up')
                    ->description('Total expenses this year')
                    ->color('info'),

                Stat::make('Pending Approvals', (string) $pendingCount)
                    ->icon('phosphor-hourglass')
                    ->description('Expenses awaiting approval')
                    ->color($pendingCount > 0 ? 'warning' : 'success')
                    ->url(route('filament.admin.resources.expenses.index', [
                        'tenant' => $gym->slug,
                        'tableFilters' => [
                            'status' => [
                                'values' => [ExpenseStatus::PENDING->value],
                            ],
                        ],
                    ])),
            ];
        }
        );
    }
}
