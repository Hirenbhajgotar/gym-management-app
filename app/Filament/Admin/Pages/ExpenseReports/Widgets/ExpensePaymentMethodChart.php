<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\ExpenseReports\Widgets;

use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\Expense;
use App\Models\Gym;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Livewire\Attributes\Reactive;

class ExpensePaymentMethodChart extends BaseChartWidget
{
    protected static ?string $heading = 'Expenses by Payment Method';

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 1;

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

        $methodData = $expenses
            ->groupBy('payment_method')
            ->map(function ($items, $method) {
                $paymentMethod = ExpensePaymentMethod::tryFrom($method);

                return [
                    'name' => $paymentMethod?->getLabel() ?? 'Unknown',
                    'total' => $items->sum('amount'),
                    'count' => $items->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        if ($methodData->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $colors = [
            '#6366f1', // Indigo
            '#22c55e', // Green
            '#eab308', // Yellow
            '#ef4444', // Red
            '#3b82f6', // Blue
            '#f97316', // Orange
            '#8b5cf6', // Violet
            '#64748b', // Slate
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Amount (₹)',
                    'data' => $methodData->pluck('total')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $methodData->count()),
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $methodData->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: true,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                return '₹' + context.parsed.x.toLocaleString('en-IN');
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: { display: true, text: 'Amount (₹)' },
                    },
                    y: {
                        grid: { display: false },
                    },
                },
            }
        JS);
    }
}
