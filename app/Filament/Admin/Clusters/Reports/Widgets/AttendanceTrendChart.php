<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\Attendances;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class AttendanceTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Daily Attendance Trend';

    protected static ?string $pollingInterval = null;

    public ?array $filters = [];

    protected function getData(): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = "widget:attendance_trend:gym:{$gymId}:" . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, [now()->addMinutes(10), now()->addMinutes(30)], function () use ($gymId) {
            $startDate = $this->filters['start_date'] ?? now()->subMonth()->format('Y-m-d');
            $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');

            $data = Attendances::where('gym_id', $gymId)
                ->whereBetween('check_in_time', [$startDate, $endDate . ' 23:59:59'])
                ->selectRaw('DATE(check_in_time) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $labels = $data->map(fn ($item) => \Carbon\Carbon::parse($item->date)->format('M d'))->toArray();
            $counts = $data->pluck('count')->toArray();

            // Calculate moving average (7-day)
            $movingAvg = [];
            foreach ($counts as $i => $count) {
                $start = max(0, $i - 6);
                $subset = array_slice($counts, $start, $i - $start + 1);
                $movingAvg[] = round(array_sum($subset) / count($subset), 1);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Check-ins',
                        'data' => $counts,
                        'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                        'borderColor' => 'rgb(59, 130, 246)',
                        'fill' => true,
                    ],
                    [
                        'label' => '7-day Avg',
                        'data' => $movingAvg,
                        'borderColor' => 'rgb(239, 68, 68)',
                        'borderDash' => [5, 5],
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
        return 'line';
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
                        'text' => 'Check-ins',
                    ],
                ],
            ],
        ];
    }
}
