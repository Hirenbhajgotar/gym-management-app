<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\Gym;
use App\Enums\LeadStatus;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\Lead;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class LeadFunnelChart extends BaseChartWidget
{
    protected static ?string $heading = 'Lead Status Distribution';

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

        $cacheKey = ReportCacheService::WIDGET_LEAD_FUNNEL . $gymId . ':' . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $startDate = $this->filters['start_date'] ?? now()->subMonths(6)->format('Y-m-d');
            $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');

            $statuses = [
                LeadStatus::NEW->value => ['label' => 'New', 'color' => 'rgb(59, 130, 246)'],
                LeadStatus::CONTACTED->value => ['label' => 'Contacted', 'color' => 'rgb(99, 102, 241)'],
                LeadStatus::CONVERTED->value => ['label' => 'Converted', 'color' => 'rgb(16, 185, 129)'],
                LeadStatus::LOST->value => ['label' => 'Lost', 'color' => 'rgb(239, 68, 68)'],
            ];

            $data = [];
            $labels = [];
            $colors = [];

            foreach ($statuses as $statusValue => $config) {
                $count = Lead::query()->where('gym_id', $gymId)
                    ->where('status', $statusValue)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();

                if ($count > 0) {
                    $data[] = $count;
                    $labels[] = $config['label'];
                    $colors[] = $config['color'];
                }
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Leads',
                        'data' => $data,
                        'backgroundColor' => $colors,
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
            'indexAxis' => 'y', // Horizontal bar for funnel effect
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Leads',
                    ],
                ],
            ],
        ];
    }
}
