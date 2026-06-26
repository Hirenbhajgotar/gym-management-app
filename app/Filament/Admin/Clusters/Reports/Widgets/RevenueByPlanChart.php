<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\Gym;
use App\Enums\PaymentStatus;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\MemberPayment;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class RevenueByPlanChart extends BaseChartWidget
{
    protected static ?string $heading = 'Revenue by Membership Plan';

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

        $cacheKey = ReportCacheService::WIDGET_REVENUE_BY_PLAN . $gymId . ':' . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $startDate = $this->filters['start_date'] ?? now()->startOfYear()->format('Y-m-d');
            $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');

            $distribution = MemberPayment::query()->where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->with('membershipPlan')
                ->get()
                ->groupBy('membership_plan_id')
                ->map(function ($payments, $planId) {
                    $plan = $payments->first()->membershipPlan;

                    return [
                        'name' => $plan?->name ?? 'No Plan',
                        'total' => $payments->sum('amount'),
                        'count' => $payments->count(),
                    ];
                })
                ->sortByDesc('total')
                ->values();

            $colors = [
                'rgb(16, 185, 129)',   // green
                'rgb(59, 130, 246)',   // blue
                'rgb(249, 115, 22)',   // orange
                'rgb(139, 92, 246)',   // purple
                'rgb(236, 72, 153)',   // pink
                'rgb(20, 184, 166)',   // teal
                'rgb(251, 191, 36)',   // amber
                'rgb(99, 102, 241)',   // indigo
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
        return 'doughnut';
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
