<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\Gym;
use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\GymMember;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class MemberStatusChart extends BaseChartWidget
{
    protected static ?string $heading = 'Membership Status Distribution';

    public ?array $filters = [];

    protected static ?string $pollingInterval = null;

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

        $cacheKey = ReportCacheService::WIDGET_MEMBER_STATUS . $gymId . ':' . md5(json_encode($this->filters));

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $active = GymMember::query()->where('gym_id', $gymId)->membershipActive()->count();
            $expired = GymMember::query()->where('gym_id', $gymId)->membershipExpired()->count();
            $expiringSoon = GymMember::query()->where('gym_id', $gymId)
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
