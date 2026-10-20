<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Trainer;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class TrainerUtilizationWidget extends BaseChartWidget
{
    protected static ?string $heading = 'Trainer Utilization Overview';

    protected static ?string $pollingInterval = null;

    /**
     * Get Livewire listeners for real-time updates via Echo.
     */
    protected function getListeners(): array
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        return [
            "echo-private:reports.{$gymId},.TrainerStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => '$refresh',
        ];
    }

    protected function getData(): array
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = ReportCacheService::WIDGET_TRAINER_UTILIZATION . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $totalTrainers = Trainer::query()->where('gym_id', $gymId)->count();
            $trainersWithClients = Trainer::query()->where('gym_id', $gymId)
                ->whereHas('members', fn (Builder $q) => $q->notArchived()->membershipActive())
                ->count();
            $trainersWithoutClients = $totalTrainers - $trainersWithClients;

            // Member coverage
            $membersWithTrainer = GymMember::query()->where('gym_id', $gymId)
                ->notArchived()
                ->membershipActive()
                ->whereNotNull('trainer_id')
                ->count();

            $membersWithoutTrainer = GymMember::query()->where('gym_id', $gymId)
                ->notArchived()
                ->membershipActive()
                ->whereNull('trainer_id')
                ->count();

            return [
                'datasets' => [
                    [
                        'label' => 'Trainers',
                        'data' => [$trainersWithClients, $trainersWithoutClients],
                        'backgroundColor' => [
                            'rgb(16, 185, 129)', // With clients
                            'rgb(239, 68, 68)',  // Without clients
                        ],
                    ],
                ],
                'labels' => ['With Clients', 'Without Clients'],
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
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
