<?php

namespace App\Filament\Admin\Pages;

use App\Enums\DefaultRole;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
use App\Filament\Admin\Pages\ExpenseReports\Widgets\ExpenseCategoryChart;
use App\Filament\Admin\Pages\ExpenseReports\Widgets\ExpenseMonthlyTrendChart;
use App\Filament\Admin\Pages\ExpenseReports\Widgets\ExpensePaymentMethodChart;
use App\Filament\BasePages\BasePage;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseReports extends BasePage implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Expense Reports';

    protected static ?string $navigationGroup = 'Expense Management';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.expense-reports';

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['page'],
            DefaultRole::RECEPTIONIST->value => [],
            DefaultRole::TRAINER->value => [],
        ];
    }

    public ?array $filters = [];

    /**
     * Get the current tenant (gym) ID.
     */
    protected function getGymId(): ?int
    {
        return Filament::getTenant()?->id;
    }

    /**
     * Get Livewire listeners for real-time updates via Echo.
     */
    protected function getListeners(): array
    {
        $gymId = $this->getGymId();

        return [
            "echo-private:reports.{$gymId},.ExpenseStatsUpdated" => '$refresh',
        ];
    }

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

    public function getExpensesProperty(): Collection
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return Expense::query()
            ->where('gym_id', $gym?->id)
            ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
            ->when($this->filters['start_date'], fn ($q) => $q->whereDate('expense_date', '>=', $this->filters['start_date']))
            ->when($this->filters['end_date'], fn ($q) => $q->whereDate('expense_date', '<=', $this->filters['end_date']))
            ->when($this->filters['status'], fn ($q) => $q->whereIn('status', $this->filters['status']))
            ->when($this->filters['category_id'], fn ($q) => $q->whereIn('expense_category_id', $this->filters['category_id']))
            ->when($this->filters['payment_method'], fn ($q) => $q->where('payment_method', $this->filters['payment_method']))
            ->with(['category', 'user'])
            ->orderBy('expense_date', 'desc')
            ->get();
    }

    public function getTotalAmountProperty(): float
    {
        return $this->expenses->sum('amount');
    }

    public function getCategoryBreakdownProperty(): Collection
    {
        return $this->expenses
            ->groupBy('expense_category_id')
            ->map(function ($expenses, $categoryId) {
                $category = $expenses->first()->category;

                return [
                    'name' => $category?->name ?? 'Uncategorized',
                    'color' => $category?->color ?? 'gray',
                    'count' => $expenses->count(),
                    'total' => $expenses->sum('amount'),
                    'percentage' => $this->totalAmount > 0
                        ? round(($expenses->sum('amount') / $this->totalAmount) * 100, 1)
                        : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    public function getMonthlyTrendProperty(): Collection
    {
        return $this->expenses
            ->groupBy(fn ($e) => $e->expense_date->format('Y-m'))
            ->map(function ($expenses, $month) {
                return [
                    'month' => \Carbon\Carbon::parse($month . '-01')->format('M Y'),
                    'total' => $expenses->sum('amount'),
                    'count' => $expenses->count(),
                ];
            })
            ->sortKeys()
            ->values();
    }

    public function getPaymentMethodBreakdownProperty(): Collection
    {
        return $this->expenses
            ->groupBy('payment_method')
            ->map(function ($expenses, $method) {
                $paymentMethod = ExpensePaymentMethod::tryFrom($method);

                return [
                    'name' => $paymentMethod?->getLabel() ?? 'Unknown',
                    'count' => $expenses->count(),
                    'total' => $expenses->sum('amount'),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    protected function getHeaderActions(): array
    {
        return [
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
        ];
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

    public static function getNavigationBadge(): ?string
    {
        return null;
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
