<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Contracts\HasFilamentPermissions;
use App\Enums\PaymentStatus;
use App\Models\MemberPayment;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class RevenueTrendChart extends ChartWidget implements HasFilamentPermissions
{
    protected static ?string $heading = 'Monthly Revenue Trend';

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public ?array $filters = [];

    /**
     * Get Livewire listeners for real-time updates via Echo.
     */
    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.RevenueStatsUpdated" => '$refresh',
        ];
    }

    protected function getData(): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = ReportCacheService::WIDGET_REVENUE_TREND . $gymId . ':' . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $startDate = $this->filters['start_date'] ?? now()->startOfYear()->format('Y-m-d');
            $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');

            $data = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total, COUNT(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $labels = $data->map(fn ($item) => \Carbon\Carbon::parse($item->month . '-01')->format('M Y'))->toArray();
            $totals = $data->pluck('total')->toArray();
            $counts = $data->pluck('count')->toArray();

            return [
                'datasets' => [
                    [
                        'label' => 'Revenue (₹)',
                        'data' => $totals,
                        'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                        'borderColor' => 'rgb(16, 185, 129)',
                        'fill' => true,
                        'yAxisID' => 'y',
                    ],
                    [
                        'label' => 'Transactions',
                        'data' => $counts,
                        'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                        'borderColor' => 'rgb(59, 130, 246)',
                        'fill' => false,
                        'type' => 'line',
                        'yAxisID' => 'y1',
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (₹)',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Transactions',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }
}
