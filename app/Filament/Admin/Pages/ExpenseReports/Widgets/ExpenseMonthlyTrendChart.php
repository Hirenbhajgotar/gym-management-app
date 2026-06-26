<?php

namespace App\Filament\Admin\Pages\ExpenseReports\Widgets;

use Illuminate\Support\Facades\Date;
use App\Models\Gym;
use App\Enums\ExpenseStatus;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\Expense;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Livewire\Attributes\Reactive;

class ExpenseMonthlyTrendChart extends BaseChartWidget
{
    protected static ?string $heading = 'Monthly Expense Trend';

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?array $filters = [];

    /**
     * Get Livewire listeners for real-time updates via Echo.
     */
    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.ExpenseStatsUpdated" => '$refresh',
        ];
    }

    protected function getData(): array
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        $expenses = Expense::query()
            ->where('gym_id', $gym?->id)
            ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
            ->when($this->filters['start_date'] ?? null, fn ($q) => $q->whereDate('expense_date', '>=', $this->filters['start_date']))
            ->when($this->filters['end_date'] ?? null, fn ($q) => $q->whereDate('expense_date', '<=', $this->filters['end_date']))
            ->when($this->filters['status'] ?? null, fn ($q) => $q->whereIn('status', $this->filters['status']))
            ->when($this->filters['category_id'] ?? null, fn ($q) => $q->whereIn('expense_category_id', $this->filters['category_id']))
            ->when($this->filters['payment_method'] ?? null, fn ($q) => $q->where('payment_method', $this->filters['payment_method']))
            ->get();

        $monthlyData = $expenses
            ->groupBy(fn ($e) => $e->expense_date->format('Y-m'))
            ->map(function ($items, $month) {
                return [
                    'month' => Date::parse($month . '-01')->format('M Y'),
                    'total' => $items->sum('amount'),
                    'count' => $items->count(),
                ];
            })
            ->sortKeys()
            ->values();

        if ($monthlyData->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Expenses (₹)',
                    'data' => $monthlyData->pluck('total')->toArray(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Number of Expenses',
                    'data' => $monthlyData->pluck('count')->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [5, 5],
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $monthlyData->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                if (context.dataset.label.includes('Total')) {
                                    return context.dataset.label + ': ₹' + context.parsed.y.toLocaleString('en-IN');
                                }
                                return context.dataset.label + ': ' + context.parsed.y;
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        grid: { color: 'rgba(99, 102, 241, 0.1)' },
                        title: { display: true, text: 'Amount (₹)' },
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Count' },
                    },
                },
            }
        JS);
    }
}
