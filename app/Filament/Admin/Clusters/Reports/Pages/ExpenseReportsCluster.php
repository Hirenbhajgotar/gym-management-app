<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Pages\ExpenseReports\Widgets\ExpenseCategoryChart;
use App\Filament\Admin\Pages\ExpenseReports\Widgets\ExpenseMonthlyTrendChart;
use App\Filament\Admin\Pages\ExpenseReports\Widgets\ExpensePaymentMethodChart;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Traits\HasReportFilters;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseReportsCluster extends Page implements HasForms
{
    use HasReportFilters;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-receipt';

    protected static ?string $navigationLabel = 'Expense Reports';

    protected static ?string $title = 'Expense Analytics';

    protected static ?int $navigationSort = 6;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.expense-reports';

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'category_id' => null,
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
                            ->default(now()->startOfYear())
                            ->native(false),

                        Forms\Components\DatePicker::make('end_date')
                            ->default(now())
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->options(ExpenseStatus::toSelectOptions())
                            ->placeholder('All Statuses')
                            ->multiple(),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(
                                ExpenseCategory::where('gym_id', $gym?->id)
                                    ->active()
                                    ->pluck('name', 'id')
                            )
                            ->placeholder('All Categories')
                            ->multiple(),

                        Forms\Components\Select::make('payment_method')
                            ->options(ExpensePaymentMethod::toSelectOptions())
                            ->placeholder('All Methods'),
                    ])
                    ->columns(5),
            ])
            ->statePath('filters');
    }

    /**
     * Get filtered expenses.
     */
    public function getFilteredExpensesQuery()
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return Expense::query()
            ->where('gym_id', $gym?->id)
            ->when($this->filters['start_date'], fn ($q) => $q->whereDate('expense_date', '>=', $this->filters['start_date']))
            ->when($this->filters['end_date'], fn ($q) => $q->whereDate('expense_date', '<=', $this->filters['end_date']))
            ->when($this->filters['status'], fn ($q) => $q->whereIn('status', $this->filters['status']))
            ->when($this->filters['category_id'], fn ($q) => $q->whereIn('expense_category_id', $this->filters['category_id']))
            ->when($this->filters['payment_method'], fn ($q) => $q->where('payment_method', $this->filters['payment_method']));
    }

    /**
     * Get summary statistics.
     */
    public function getSummaryStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = $this->getReportCacheKey('expense_summary', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(10), now()->addMinutes(30)], function () use ($gymId) {
            $query = $this->getFilteredExpensesQuery();

            // Total expenses
            $totalExpenses = $query->clone()
                ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
                ->sum('amount');

            $transactionCount = $query->clone()
                ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
                ->count();

            // Pending expenses
            $pendingAmount = Expense::where('gym_id', $gymId)
                ->where('status', ExpenseStatus::PENDING)
                ->sum('amount');

            $pendingCount = Expense::where('gym_id', $gymId)
                ->where('status', ExpenseStatus::PENDING)
                ->count();

            // This month vs last month
            $thisMonthExpenses = Expense::where('gym_id', $gymId)
                ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount');

            $lastMonthExpenses = Expense::where('gym_id', $gymId)
                ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
                ->whereMonth('expense_date', now()->subMonth()->month)
                ->whereYear('expense_date', now()->subMonth()->year)
                ->sum('amount');

            $monthlyChange = $this->calculatePercentageChange($thisMonthExpenses, $lastMonthExpenses);

            // Average expense
            $avgExpense = $transactionCount > 0 ? round($totalExpenses / $transactionCount, 2) : 0;

            // Top category
            $topCategory = $query->clone()
                ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
                ->with('category')
                ->get()
                ->groupBy('expense_category_id')
                ->sortByDesc(fn ($items) => $items->sum('amount'))
                ->first();

            return [
                'total_expenses' => $totalExpenses,
                'transaction_count' => $transactionCount,
                'avg_expense' => $avgExpense,
                'pending_amount' => $pendingAmount,
                'pending_count' => $pendingCount,
                'this_month' => $thisMonthExpenses,
                'last_month' => $lastMonthExpenses,
                'monthly_change' => $monthlyChange,
                'monthly_change_color' => $monthlyChange > 0 ? 'danger' : ($monthlyChange < 0 ? 'success' : 'gray'),
                'top_category' => $topCategory?->first()?->category?->name ?? 'N/A',
            ];
        });
    }

    public function getExpensesProperty(): Collection
    {
        return $this->getFilteredExpensesQuery()
            ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
            ->with(['category', 'user'])
            ->orderBy('expense_date', 'desc')
            ->get();
    }

    public function getCategoryBreakdownProperty(): Collection
    {
        $totalAmount = $this->expenses->sum('amount');

        return $this->expenses
            ->groupBy('expense_category_id')
            ->map(function ($expenses, $categoryId) use ($totalAmount) {
                $category = $expenses->first()->category;

                return [
                    'name' => $category?->name ?? 'Uncategorized',
                    'color' => $category?->color ?? 'gray',
                    'count' => $expenses->count(),
                    'total' => $expenses->sum('amount'),
                    'percentage' => $totalAmount > 0
                        ? round(($expenses->sum('amount') / $totalAmount) * 100, 1)
                        : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    protected function getHeaderActions(): array
    {
        return array_merge([
            Action::make('export')
                ->label('Export to Excel')
                ->icon('phosphor-download')
                ->color('success')
                ->action(function () {
                    return $this->exportToExcel();
                }),

            Action::make('refresh')
                ->label('Refresh')
                ->icon('phosphor-arrows-clockwise')
                ->color('gray')
                ->action(fn () => $this->dispatch('$refresh')),
        ], $this->getFilterActions(), $this->getExportActions());
    }

    public function exportToExcel()
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        $filename = 'expense-report-' . $gym->slug . '-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new \App\Exports\ExpenseReportExport(
                $this->expenses,
                $this->categoryBreakdown,
                $this->filters
            ),
            $filename
        );
    }

    protected function getFooterWidgets(): array
    {
        return [
            ExpenseCategoryChart::make(['filters' => $this->filters]),
            ExpensePaymentMethodChart::make(['filters' => $this->filters]),
            ExpenseMonthlyTrendChart::make(['filters' => $this->filters]),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }
}
