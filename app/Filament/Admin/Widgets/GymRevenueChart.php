<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\BaseWidgets\BaseChartWidget;
use App\Models\Gym;
use App\Models\MemberPayment;
use App\Services\DashboardCacheService;
use App\Traits\HasTenantAccess;
use Flowframe\Trend\Trend;
use Illuminate\Support\Facades\Cache;

class GymRevenueChart extends BaseChartWidget
{
    use HasTenantAccess;

    protected static ?string $heading = 'Monthly Revenue (Past 12 Months)';

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 4;

    // protected int|string|array $columnSpan = 'full';

    protected function getListeners(): array
    {
        $gymId = $this->getGymId();

        return [
            "echo-private:reports.{$gymId},.RevenueStatsUpdated" => '$refresh',
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->hasRole('gym_owner');
    }

    // protected function getFilters(): ?array
    // {
    //     // Fetch all gyms for the current gym_owner
    //     $gyms = Gym::where('owner_id', auth()->user()->id)
    //         ->pluck('name', 'id')
    //         ->toArray();

    //     // Add "All Gyms" as the default option
    //     return array_merge(
    //         ['all' => 'All Gyms'],
    //         $gyms
    //     );
    // }

    protected function getData(): array
    {
        $tenantId = $this->getGymId();

        if (! $tenantId) {
            return ['datasets' => [], 'labels' => []];
        }

        return Cache::flexible(
            DashboardCacheService::REVENUE_CHART . $tenantId,
            DashboardCacheService::getCacheTtl(),
            function () use ($tenantId) {
                // Define the start and end date for the past 12 months
                $endDate = now();
                $startDate = now()->subMonths(11)->startOfMonth();

                // Base query for MemberPayment
                $query = MemberPayment::query()
                    ->whereHas('member.gym', fn ($query) => $query->where('id', $tenantId));

                // Apply gym filter if not "All Gyms"
                $selectedGym = $this->filter;
                // if ($selectedGym && $selectedGym !== 'all') {
                //     $query->whereHas('member.gym', fn($query) => $query->where('id', $selectedGym));
                // }

                $payments = Trend::query($query)
                    ->dateColumn('payment_date')
                    ->between(
                        start: $startDate,
                        end: $endDate
                    )
                    ->perMonth()
                    ->sum('amount');

                // Prepare data for the chart
                $labels = [];
                $data = array_fill(0, 12, 0); // Initialize array for 12 months with 0

                // Generate labels for the past 12 months
                for ($i = 11; $i >= 0; $i--) {
                    $month = now()->subMonths($i);
                    $labels[] = $month->format('M Y'); // e.g., "May 2024", "Jun 2024", ...
                }

                // Map Trend data to the chart data
                foreach ($payments as $index => $payment) {
                    $data[$index] = $payment->aggregate; // Trend package returns 'aggregate' for the sum
                }

                return [
                    'datasets' => [
                        [
                            'label' => 'Revenue (₹)',
                            'data' => $data,
                            'borderColor' => 'rgba(75, 192, 192, 1)',
                            'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                            'fill' => true,
                        ],
                    ],
                    'labels' => $labels,
                ];
            }
        );
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (₹)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Month',
                    ],
                ],
            ],
        ];
    }
}
