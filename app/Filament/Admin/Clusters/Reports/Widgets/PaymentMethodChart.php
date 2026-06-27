<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\Gym;
use App\Enums\PaymentStatus;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\MemberPayment;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class PaymentMethodChart extends BaseChartWidget
{
    protected static ?string $heading = 'Revenue by Payment Method';

    protected static ?string $pollingInterval = null;

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
        /** @var Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = ReportCacheService::WIDGET_PAYMENT_METHOD . $gymId . ':' . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $startDate = $this->filters['start_date'] ?? now()->startOfYear()->format('Y-m-d');
            $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');

            $distribution = MemberPayment::query()->where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('payment_method')
                ->orderByDesc('total')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => ucfirst(str_replace('_', ' ', $item->payment_method ?? 'Unknown')),
                        'total' => $item->total,
                        'count' => $item->count,
                    ];
                });

            $colors = [
                'rgb(16, 185, 129)',   // green
                'rgb(59, 130, 246)',   // blue
                'rgb(249, 115, 22)',   // orange
                'rgb(139, 92, 246)',   // purple
                'rgb(236, 72, 153)',   // pink
            ];

            return [
                'datasets' => [
                    [
                        'label' => 'Revenue (₹)',
                        'data' => $distribution->pluck('total')->toArray(),
                        'backgroundColor' => array_slice($colors, 0, $distribution->count()),
                    ],
                ],
                'labels' => $distribution->pluck('name')->toArray(),
            ];
        });
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
            ],
        ];
    }
}
