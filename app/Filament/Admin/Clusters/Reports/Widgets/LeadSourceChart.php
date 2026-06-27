<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\Gym;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\Lead;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class LeadSourceChart extends BaseChartWidget
{
    protected static ?string $heading = 'Leads by Source';

    protected static ?string $pollingInterval = null;

    public ?array $filters = [];

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.LeadStatsUpdated" => '$refresh',
        ];
    }

    protected function getData(): array
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = ReportCacheService::WIDGET_LEAD_SOURCE . $gymId . ':' . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $startDate = $this->filters['start_date'] ?? now()->subMonths(6)->format('Y-m-d');
            $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');

            $distribution = Lead::query()->where('gym_id', $gymId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('source, COUNT(*) as count')
                ->groupBy('source')
                ->orderByDesc('count')
                ->get()
                ->map(function ($item) {
                    $source = $item->source;

                    return [
                        'name' => $source?->getLabel() ?? ucfirst(str_replace('_', ' ', $item->source ?? 'Unknown')),
                        'count' => $item->count,
                    ];
                });

            $colors = [
                'rgb(59, 130, 246)',   // blue - Website
                'rgb(236, 72, 153)',   // pink - Facebook
                'rgb(16, 185, 129)',   // green - WhatsApp
                'rgb(139, 92, 246)',   // purple - Instagram
                'rgb(249, 115, 22)',   // orange - Referral
                'rgb(20, 184, 166)',   // teal - Walk-in
                'rgb(251, 191, 36)',   // amber - Phone
                'rgb(99, 102, 241)',   // indigo - Other
            ];

            return [
                'datasets' => [
                    [
                        'label' => 'Leads',
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
