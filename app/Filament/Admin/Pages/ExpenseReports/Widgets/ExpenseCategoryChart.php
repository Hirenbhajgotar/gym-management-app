<?php

namespace App\Filament\Admin\Pages\ExpenseReports\Widgets;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

class ExpenseCategoryChart extends ChartWidget
{
    protected static ?string $heading = 'Expenses by Category';

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?array $filters = [];

    protected function getData(): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        $expenses = Expense::query()
            ->where('gym_id', $gym?->id)
            ->whereIn('status', [ExpenseStatus::APPROVED, ExpenseStatus::PAID])
            ->when($this->filters['start_date'] ?? null, fn ($q) => $q->whereDate('expense_date', '>=', $this->filters['start_date']))
            ->when($this->filters['end_date'] ?? null, fn ($q) => $q->whereDate('expense_date', '<=', $this->filters['end_date']))
            ->when($this->filters['status'] ?? null, fn ($q) => $q->whereIn('status', $this->filters['status']))
            ->when($this->filters['category_id'] ?? null, fn ($q) => $q->whereIn('expense_category_id', $this->filters['category_id']))
            ->when($this->filters['payment_method'] ?? null, fn ($q) => $q->where('payment_method', $this->filters['payment_method']))
            ->with('category')
            ->get();

        $categoryData = $expenses
            ->groupBy('expense_category_id')
            ->map(function ($items, $categoryId) {
                $category = $items->first()->category;

                return [
                    'name' => $category?->name ?? 'Uncategorized',
                    'total' => $items->sum('amount'),
                    'color' => $this->getColorHex($category?->color ?? 'gray'),
                ];
            })
            ->sortByDesc('total')
            ->values();

        if ($categoryData->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Expenses (₹)',
                    'data' => $categoryData->pluck('total')->toArray(),
                    'backgroundColor' => $categoryData->pluck('color')->toArray(),
                    'borderColor' => $categoryData->pluck('color')->toArray(),
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $categoryData->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => true,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 15,
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) {
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return context.label + ': ₹' + value.toLocaleString('en-IN') + ' (' + percentage + '%)';
                        }",
                    ],
                ],
            ],
            'cutout' => '60%',
        ];
    }

    private function getColorHex(string $color): string
    {
        return match ($color) {
            'success' => '#22c55e',
            'info' => '#3b82f6',
            'warning' => '#eab308',
            'danger' => '#ef4444',
            'primary' => '#6366f1',
            'secondary' => '#64748b',
            default => '#6b7280',
        };
    }
}
