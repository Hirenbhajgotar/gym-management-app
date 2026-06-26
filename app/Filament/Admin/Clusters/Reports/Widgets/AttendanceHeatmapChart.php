<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\Gym;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\Attendances;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class AttendanceHeatmapChart extends BaseChartWidget
{
    protected static ?string $heading = 'Check-ins by Day of Week';

    protected static ?string $pollingInterval = null;

    public ?array $filters = [];

    protected function getData(): array
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = "widget:attendance_heatmap:gym:{$gymId}:" . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, [now()->addMinutes(15), now()->addHours(1)], function () use ($gymId) {
            $startDate = $this->filters['start_date'] ?? now()->subMonth()->format('Y-m-d');
            $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');

            $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

            $distribution = Attendances::query()->where('gym_id', $gymId)
                ->whereBetween('check_in_time', [$startDate, $endDate . ' 23:59:59'])
                ->selectRaw('DAYOFWEEK(check_in_time) as day, COUNT(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day')
                ->toArray();

            $data = [];
            $labels = [];

            // MySQL DAYOFWEEK returns 1=Sunday, 2=Monday, etc.
            // Reorder to start with Monday
            $order = [2, 3, 4, 5, 6, 7, 1]; // Mon-Sun

            foreach ($order as $dayNum) {
                $labels[] = $dayNames[$dayNum - 1];
                $data[] = $distribution[$dayNum] ?? 0;
            }

            // Generate gradient colors based on values
            $maxCount = max($data) ?: 1;
            $colors = array_map(function ($count) use ($maxCount) {
                $intensity = $maxCount > 0 ? $count / $maxCount : 0;

                // From light green to dark green
                $r = round(200 - ($intensity * 150));
                $g = round(220 - ($intensity * 35));
                $b = round(200 - ($intensity * 150));

                return "rgb({$r}, {$g}, {$b})";
            }, $data);

            return [
                'datasets' => [
                    [
                        'label' => 'Check-ins',
                        'data' => $data,
                        'backgroundColor' => $colors,
                        'borderColor' => 'rgb(75, 85, 99)',
                        'borderWidth' => 1,
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
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Check-ins',
                    ],
                ],
            ],
        ];
    }
}
