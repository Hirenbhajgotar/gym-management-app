<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Enums\DefaultRole;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\Trainer;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class TrainerClientChart extends BaseChartWidget
{
    protected static ?string $heading = 'Clients per Trainer';

    protected static ?string $pollingInterval = null;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['widget'],
            DefaultRole::RECEPTIONIST->value => ['widget'],
            DefaultRole::TRAINER->value => [],
        ];
    }

    /**
     * Get Livewire listeners for real-time updates via Echo.
     */
    protected function getListeners(): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        return [
            "echo-private:reports.{$gymId},.TrainerStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => '$refresh',
        ];
    }

    protected function getData(): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = ReportCacheService::WIDGET_TRAINER_CLIENTS . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $trainers = Trainer::where('gym_id', $gymId)
                ->withCount(['members' => fn ($q) => $q->membershipActive()])
                ->orderByDesc('members_count')
                ->limit(10) // Top 10 trainers
                ->get();

            $colors = [
                'rgb(59, 130, 246)',
                'rgb(16, 185, 129)',
                'rgb(249, 115, 22)',
                'rgb(139, 92, 246)',
                'rgb(236, 72, 153)',
                'rgb(20, 184, 166)',
                'rgb(251, 191, 36)',
                'rgb(99, 102, 241)',
                'rgb(244, 63, 94)',
                'rgb(34, 197, 94)',
            ];

            return [
                'datasets' => [
                    [
                        'label' => 'Active Clients',
                        'data' => $trainers->pluck('members_count')->toArray(),
                        'backgroundColor' => array_slice($colors, 0, $trainers->count()),
                    ],
                ],
                'labels' => $trainers->pluck('name')->toArray(),
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
            'indexAxis' => 'y', // Horizontal bar
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
                        'text' => 'Number of Clients',
                    ],
                ],
            ],
        ];
    }
}
