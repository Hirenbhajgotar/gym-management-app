<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\GymMember;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class MemberStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Membership Status Distribution';

    protected static ?string $pollingInterval = '300s';

    public ?array $filters = [];

    protected function getData(): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();
        $gymId = $gym?->id;

        $cacheKey = "widget:member_status:gym:{$gymId}:" . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, [now()->addMinutes(15), now()->addHours(1)], function () use ($gymId) {
            $active = GymMember::where('gym_id', $gymId)->membershipActive()->count();
            $expired = GymMember::where('gym_id', $gymId)->membershipExpired()->count();
            $expiringSoon = GymMember::where('gym_id', $gymId)
                ->where('membership_end', '>=', now())
                ->where('membership_end', '<=', now()->addDays(7))
                ->count();

            return [
                'datasets' => [
                    [
                        'label' => 'Members',
                        'data' => [$active, $expiringSoon, $expired],
                        'backgroundColor' => [
                            'rgb(16, 185, 129)', // success - active
                            'rgb(245, 158, 11)', // warning - expiring soon
                            'rgb(239, 68, 68)',  // danger - expired
                        ],
                    ],
                ],
                'labels' => ['Active', 'Expiring Soon (7 days)', 'Expired'],
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
