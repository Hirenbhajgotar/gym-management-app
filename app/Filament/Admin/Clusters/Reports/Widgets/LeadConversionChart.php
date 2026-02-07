<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class LeadConversionChart extends ChartWidget
{
    protected static ?string $heading = 'Monthly Lead Conversion';

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

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
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = "widget:lead_conversion:gym:{$gymId}:" . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, [now()->addMinutes(15), now()->addHours(1)], function () use ($gymId) {
            $startDate = $this->filters['start_date'] ?? now()->subMonths(6)->format('Y-m-d');
            $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');

            // Get monthly lead creation
            $newLeads = Lead::where('gym_id', $gymId)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('count', 'month');

            // Get monthly conversions (based on updated_at when status changed to converted)
            $conversions = Lead::where('gym_id', $gymId)
                ->where('status', LeadStatus::CONVERTED)
                ->whereDate('updated_at', '>=', $startDate)
                ->whereDate('updated_at', '<=', $endDate)
                ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m') as month, COUNT(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('count', 'month');

            // Merge months
            $allMonths = $newLeads->keys()->merge($conversions->keys())->unique()->sort();

            $labels = [];
            $newLeadData = [];
            $conversionData = [];

            foreach ($allMonths as $month) {
                $labels[] = \Carbon\Carbon::parse($month . '-01')->format('M Y');
                $newLeadData[] = $newLeads->get($month, 0);
                $conversionData[] = $conversions->get($month, 0);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'New Leads',
                        'data' => $newLeadData,
                        'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                        'borderColor' => 'rgb(59, 130, 246)',
                    ],
                    [
                        'label' => 'Converted',
                        'data' => $conversionData,
                        'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                        'borderColor' => 'rgb(16, 185, 129)',
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
                        'text' => 'Number of Leads',
                    ],
                ],
            ],
        ];
    }
}
