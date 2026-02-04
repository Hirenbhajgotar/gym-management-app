<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentStatus;
use App\Exports\RevenueReportExport;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Clusters\Reports\Widgets\PaymentMethodChart;
use App\Filament\Admin\Clusters\Reports\Widgets\RevenueByPlanChart;
use App\Filament\Admin\Clusters\Reports\Widgets\RevenueForecastChart;
use App\Filament\Admin\Clusters\Reports\Widgets\RevenueTrendChart;
use App\Models\Expense;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Traits\HasReportFilters;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class RevenueReports extends Page implements HasForms
{
    use HasReportFilters;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-currency-inr';

    protected static ?string $navigationLabel = 'Revenue Reports';

    protected static ?string $title = 'Revenue Analytics';

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.revenue-reports';

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'membership_plan_id' => null,
            'payment_method' => null,
        ];
    }

    public function form(Form $form): Form
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return $form
            ->schema([
                Forms\Components\Section::make('Report Filters')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('From Date')
                            ->default(now()->startOfYear())
                            ->native(false),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('To Date')
                            ->default(now())
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Payment Status')
                            ->options(PaymentStatus::class)
                            ->placeholder('All Status'),

                        Forms\Components\Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->options(
                                MembershipPlan::where('gym_id', $gym?->id)
                                    ->pluck('name', 'id')
                            )
                            ->placeholder('All Plans'),
                    ])
                    ->columns(4),
            ])
            ->statePath('filters');
    }

    /**
     * Get filtered payments query builder.
     */
    public function getFilteredPaymentsQuery()
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return MemberPayment::query()
            ->where('gym_id', $gym?->id)
            ->when($this->filters['start_date'], fn ($q) => $q->whereDate('payment_date', '>=', $this->filters['start_date']))
            ->when($this->filters['end_date'], fn ($q) => $q->whereDate('payment_date', '<=', $this->filters['end_date']))
            ->when($this->filters['status'], fn ($q) => $q->where('status', $this->filters['status']))
            ->when($this->filters['membership_plan_id'], fn ($q) => $q->where('membership_plan_id', $this->filters['membership_plan_id']));
    }

    /**
     * Get summary statistics.
     */
    public function getSummaryStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = $this->getReportCacheKey('revenue_summary', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(10), now()->addMinutes(30)], function () use ($gymId) {
            // Total revenue (completed payments)
            $totalRevenue = $this->getFilteredPaymentsQuery()
                ->clone()
                ->where('status', PaymentStatus::COMPLETED)
                ->sum('amount');

            // Transaction count
            $transactionCount = $this->getFilteredPaymentsQuery()
                ->clone()
                ->where('status', PaymentStatus::COMPLETED)
                ->count();

            // Average transaction value
            $avgTransaction = $transactionCount > 0 ? round($totalRevenue / $transactionCount, 2) : 0;

            // Pending payments
            $pendingPayments = $this->getFilteredPaymentsQuery()
                ->clone()
                ->where('status', PaymentStatus::PENDING)
                ->sum('amount');

            // Active members count
            $activeMembers = GymMember::where('gym_id', $gymId)->membershipActive()->count();

            // ARPM (Average Revenue Per Member)
            $arpm = $activeMembers > 0 ? round($totalRevenue / $activeMembers, 2) : 0;

            // This month vs last month comparison
            $thisMonthRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount');

            $lastMonthRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereMonth('payment_date', now()->subMonth()->month)
                ->whereYear('payment_date', now()->subMonth()->year)
                ->sum('amount');

            $monthlyChange = $this->calculatePercentageChange($thisMonthRevenue, $lastMonthRevenue);

            // Today's collection
            $todayCollection = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereDate('payment_date', today())
                ->sum('amount');

            return [
                'total_revenue' => $totalRevenue,
                'transaction_count' => $transactionCount,
                'avg_transaction' => $avgTransaction,
                'pending_payments' => $pendingPayments,
                'arpm' => $arpm,
                'this_month' => $thisMonthRevenue,
                'last_month' => $lastMonthRevenue,
                'monthly_change' => $monthlyChange,
                'monthly_change_color' => $this->getTrendColor($monthlyChange),
                'today_collection' => $todayCollection,
            ];
        });
    }

    /**
     * Get P&L Data.
     */
    public function getPnLData(): array
    {
        $gymId = $this->getGymId();
        // Include start/end date in cache key to invalidate when filters change
        $cacheKey = $this->getReportCacheKey('pnl_data_v2', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(10), now()->addMinutes(30)], function () use ($gymId) {
            $startDate = $this->filters['start_date'] ? \Carbon\Carbon::parse($this->filters['start_date']) : now()->startOfYear();
            $endDate = $this->filters['end_date'] ? \Carbon\Carbon::parse($this->filters['end_date']) : now();

            // Create monthly periods between start and end date
            $period = CarbonPeriod::create($startDate, '1 month', $endDate);
            $data = [];

            foreach ($period as $date) {
                $startOfMonth = $date->copy()->startOfMonth();
                $endOfMonth = $date->copy()->endOfMonth();

                // Adjust for query range if the month is partial (start/end of range)
                // Although strictly for P&L "Monthly Breakdown" usually implies the whole month's context,
                // let's stick to the filter range for strictness or whole month for general P&L?
                // Usually P&L is done by whole accounting periods (months).
                // Let's use the intersection of the month and the selected range to be precise,
                // OR just use the whole month if the user selected a range converting multiple months.
                // Let's look at the existing logic. It uses `startOfMonth` and `endOfMonth`.
                // Let's stick to full months for the breakdown rows to be cleaner,
                // BUT we should respect the outer bounds if possible.
                // Actually, existing `getFilteredPaymentsQuery` uses precise dates.
                // For a P&L table, showing "January" usually means the whole January.
                // Let's use the whole month for the breakdown rows to ensure "Expenses for January" are all captured
                // even if user selected Jan 5 to Jan 25.
                // However, strictly complying with filters is safer for "Custom Range".
                // Let's use the strict range intersection for now to be consistent with other reports.

                $queryStart = $startOfMonth->max($startDate);
                $queryEnd = $endOfMonth->min($endDate);

                if ($queryStart > $queryEnd) {
                    continue;
                }

                $label = $startOfMonth->format('M Y');

                // Revenue
                $revenue = MemberPayment::where('gym_id', $gymId)
                    ->where('status', PaymentStatus::COMPLETED)
                    ->whereBetween('payment_date', [$queryStart, $queryEnd])
                    ->sum('amount');

                // Expenses (Approved or Paid)
                $expenses = Expense::where('gym_id', $gymId)
                    ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
                    ->whereBetween('expense_date', [$queryStart, $queryEnd])
                    ->sum('amount');

                $netProfit = $revenue - $expenses;
                $margin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0;

                $data[] = [
                    'label' => $label,
                    'revenue' => $revenue,
                    'expenses' => $expenses,
                    'net_profit' => $netProfit,
                    'margin' => $margin,
                ];
            }

            // Calculate totals
            $totalRevenue = collect($data)->sum('revenue');
            $totalExpenses = collect($data)->sum('expenses');
            $totalNetProfit = $totalRevenue - $totalExpenses;
            $overallMargin = $totalRevenue > 0 ? round(($totalNetProfit / $totalRevenue) * 100, 1) : 0;

            return [
                'breakdown' => $data,
                'totals' => [
                    'revenue' => $totalRevenue,
                    'expenses' => $totalExpenses,
                    'net_profit' => $totalNetProfit,
                    'margin' => $overallMargin,
                ],
            ];
        });
    }

    /**
     * Get Revenue Breakdown by Plan.
     */
    public function getRevenueBreakdown(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = $this->getReportCacheKey('revenue_breakdown', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(10), now()->addMinutes(30)], function () {
            // Use collection grouping for simplicity and relation handling
            // This retrieves all relevant payments - ensure memory limit is not hit for large datasets
            // For very large datasets, a DB::table query would be better.
            return $this->getFilteredPaymentsQuery()
                ->clone()
                ->where('status', PaymentStatus::COMPLETED)
                ->with('membershipPlan')
                ->get()
                ->groupBy(fn ($payment) => $payment->membershipPlan?->name ?? 'Other')
                ->map(function ($payments, $planName) {
                    $amount = $payments->sum('amount');
                    $count = $payments->count();

                    return [
                        'name' => $planName,
                        'amount' => $amount,
                        'transactions' => $count,
                        'avg_amount' => $count > 0 ? $amount / $count : 0,
                    ];
                })
                ->sortByDesc('amount')
                ->values()
                ->toArray();
        });
    }

    /**
     * Get Expense Breakdown by Category.
     */
    public function getExpenseBreakdown(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = $this->getReportCacheKey('expense_breakdown', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(10), now()->addMinutes(30)], function () use ($gymId) {
            $startDate = $this->filters['start_date'];
            $endDate = $this->filters['end_date'];

            return Expense::where('gym_id', $gymId)
                ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
                ->when($startDate, fn ($q) => $q->whereDate('expense_date', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->whereDate('expense_date', '<=', $endDate))
                ->with('category')
                ->get()
                ->groupBy(fn ($expense) => $expense->category?->name ?? 'Uncategorized')
                ->map(function ($expenses, $categoryName) {
                    $amount = $expenses->sum('amount');
                    $count = $expenses->count();

                    return [
                        'name' => $categoryName,
                        'amount' => $amount,
                        'change_rate' => 0, // Placeholder for valid change calculation if needed
                        'budget_variance' => 0, // Placeholder
                    ];
                })
                ->sortByDesc('amount')
                ->values()
                ->toArray();
        });
    }

    protected function getHeaderActions(): array
    {
        return array_merge([
            Action::make('export')
                ->label('Export to Excel')
                ->icon('phosphor-download')
                ->color('success')
                ->action(function () {
                    /** @var \App\Models\Gym $gym */
                    $gym = Filament::getTenant();

                    return new RevenueReportExport($gym?->id, $this->filters);
                }),

            Action::make('refresh')
                ->label('Refresh')
                ->icon('phosphor-arrows-clockwise')
                ->color('gray')
                ->action(fn () => $this->dispatch('$refresh')),
        ], $this->getFilterActions(), $this->getExportActions());
    }

    protected function getFooterWidgets(): array
    {
        return [
            RevenueForecastChart::class,
            RevenueTrendChart::make(['filters' => $this->filters]),
            RevenueByPlanChart::make(['filters' => $this->filters]),
            PaymentMethodChart::make(['filters' => $this->filters]),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }
}
