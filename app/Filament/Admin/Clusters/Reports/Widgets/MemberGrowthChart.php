<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use Illuminate\Support\Facades\Date;
use App\Models\Gym;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\GymMember;
use App\Services\ReportCacheService;
use App\Traits\HasReportFilters;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class MemberGrowthChart extends BaseChartWidget
{
    use HasReportFilters;

    protected static ?string $heading = 'Member Growth Over Time';

    protected int|string|array $columnSpan = 'full';

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
        /** @var Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = ReportCacheService::WIDGET_MEMBER_GROWTH . $gymId . ':' . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $startDate = $this->filters['start_date'] ?? now()->subYear()->format('Y-m-d');
            $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');

            $data = GymMember::query()->where('gym_id', $gymId)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $labels = $data->map(fn ($item) => Date::parse($item->month . '-01')->format('M Y'))->all();
            $counts = $data->pluck('count')->toArray();

            // Calculate cumulative total
            $cumulative = [];
            $total = 0;
            foreach ($counts as $count) {
                $total += $count;
                $cumulative[] = $total;
            }

            return [
                'datasets' => [
                    [
                        'label' => 'New Members',
                        'data' => $counts,
                        'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                        'borderColor' => 'rgb(59, 130, 246)',
                        'fill' => true,
                    ],
                    [
                        'label' => 'Cumulative Total',
                        'data' => $cumulative,
                        'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                        'borderColor' => 'rgb(16, 185, 129)',
                        'fill' => false,
                        'type' => 'line',
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
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Members',
                    ],
                ],
            ],
        ];
    }
}
