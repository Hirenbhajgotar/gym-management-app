<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\Gym;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\GymMember;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class MemberDemographicsChart extends BaseChartWidget
{
    protected static ?string $heading = 'Member Age Distribution';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    public ?array $filters = [];

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

        $cacheKey = ReportCacheService::WIDGET_MEMBER_DEMOGRAPHICS . $gymId . ':' . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            // Age groups from DOB
            $ageGroups = [
                '18-24' => 0,
                '25-34' => 0,
                '35-44' => 0,
                '45-54' => 0,
                '55+' => 0,
                'Unknown' => 0,
            ];

            $members = GymMember::query()->where('gym_id', $gymId)
                ->select('dob', 'age')
                ->get();

            foreach ($members as $member) {
                $age = null;

                if ($member->dob) {
                    $age = $member->dob->age;
                } elseif ($member->age) {
                    $age = $member->age;
                }

                if ($age === null) {
                    $ageGroups['Unknown']++;
                } elseif ($age >= 18 && $age <= 24) {
                    $ageGroups['18-24']++;
                } elseif ($age >= 25 && $age <= 34) {
                    $ageGroups['25-34']++;
                } elseif ($age >= 35 && $age <= 44) {
                    $ageGroups['35-44']++;
                } elseif ($age >= 45 && $age <= 54) {
                    $ageGroups['45-54']++;
                } elseif ($age >= 55) {
                    $ageGroups['55+']++;
                } else {
                    $ageGroups['Unknown']++;
                }
            }

            // Remove Unknown if empty
            if ($ageGroups['Unknown'] === 0) {
                unset($ageGroups['Unknown']);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Members',
                        'data' => array_values($ageGroups),
                        'backgroundColor' => [
                            'rgb(59, 130, 246)',   // blue
                            'rgb(16, 185, 129)',   // green
                            'rgb(249, 115, 22)',   // orange
                            'rgb(139, 92, 246)',   // purple
                            'rgb(236, 72, 153)',   // pink
                            'rgb(156, 163, 175)',  // gray
                        ],
                    ],
                ],
                'labels' => array_keys($ageGroups),
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
            'indexAxis' => 'y', // Horizontal bar chart
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
                        'text' => 'Number of Members',
                    ],
                ],
            ],
        ];
    }
}
