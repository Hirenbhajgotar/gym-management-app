<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\GymMember;
use App\Traits\HasReportFilters;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class MemberGrowthChart extends ChartWidget
{
    use HasReportFilters;

    protected static ?string $heading = 'Member Growth Over Time';

    protected int|string|array $columnSpan = 'full';

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

        $cacheKey = "widget:member_growth:gym:{$gymId}:" . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, [now()->addMinutes(30), now()->addHours(2)], function () use ($gymId) {
            $startDate = $this->filters['start_date'] ?? now()->subYear()->format('Y-m-d');
            $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');

            $data = GymMember::where('gym_id', $gymId)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $labels = $data->map(fn ($item) => \Carbon\Carbon::parse($item->month . '-01')->format('M Y'))->toArray();
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
