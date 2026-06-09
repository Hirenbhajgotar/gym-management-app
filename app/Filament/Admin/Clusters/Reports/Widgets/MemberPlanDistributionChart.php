<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\GymMember;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class MemberPlanDistributionChart extends BaseChartWidget
{
    protected static ?string $heading = 'Members by Membership Plan';

    public ?array $filters = [];

    protected static ?string $pollingInterval = null;

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => '$refresh',
        ];
    }

    protected function getData(): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = ReportCacheService::WIDGET_MEMBER_PLAN_DIST . $gymId . ':' . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $distribution = GymMember::where('gym_id', $gymId)
                ->with('membershipPlan')
                ->get()
                ->groupBy('gym_membership_plan_id')
                ->map(function ($members, $planId) {
                    $plan = $members->first()->membershipPlan;

                    return [
                        'name' => $plan?->name ?? 'No Plan',
                        'count' => $members->count(),
                    ];
                })
                ->sortByDesc('count')
                ->values();

            $colors = [
                'rgb(59, 130, 246)',   // blue
                'rgb(16, 185, 129)',   // green
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
                        'label' => 'Members',
                        'data' => $distribution->pluck('count')->toArray(),
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
