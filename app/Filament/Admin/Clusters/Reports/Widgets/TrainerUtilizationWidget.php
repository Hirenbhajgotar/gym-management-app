<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\GymMember;
use App\Models\Trainer;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class TrainerUtilizationWidget extends ChartWidget
{
    protected static ?string $heading = 'Trainer Utilization Overview';

    protected static ?string $pollingInterval = '300s';

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

        $cacheKey = "widget:trainer_utilization:gym:{$gymId}";

        return Cache::flexible($cacheKey, [now()->addMinutes(15), now()->addHours(1)], function () use ($gymId) {
            $totalTrainers = Trainer::where('gym_id', $gymId)->count();
            $trainersWithClients = Trainer::where('gym_id', $gymId)
                ->whereHas('members', fn ($q) => $q->membershipActive())
                ->count();
            $trainersWithoutClients = $totalTrainers - $trainersWithClients;

            // Member coverage
            $membersWithTrainer = GymMember::where('gym_id', $gymId)
                ->membershipActive()
                ->whereNotNull('trainer_id')
                ->count();

            $membersWithoutTrainer = GymMember::where('gym_id', $gymId)
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
