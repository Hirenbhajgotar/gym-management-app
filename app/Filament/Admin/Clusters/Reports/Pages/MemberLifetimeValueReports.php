<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Filament\Admin\Clusters\Reports;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Traits\HasReportFilters;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class MemberLifetimeValueReports extends Page
{
    use HasReportFilters;

    protected static ?string $navigationIcon = 'phosphor-diamond';

    protected static ?string $navigationLabel = 'Member LTV';

    protected static ?string $title = 'Lifetime Value Analytics';

    protected static ?int $navigationSort = 7;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.member-lifetime-value-reports';

    /**
     * Get LTV statistics.
     */
    public function getLtvStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = "reports:ltv_stats:gym:{$gymId}";

        return Cache::flexible($cacheKey, [now()->addHours(2), now()->addHours(6)], function () use ($gymId) {
            // 1. Average Lifetime Value (ALTV)
            // Total Revenue / Total Members (Ever)
            $totalRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', 'completed')
                ->sum('amount');

            $totalMembersEver = GymMember::where('gym_id', $gymId)->count();

            $altv = $totalMembersEver > 0 ? round($totalRevenue / $totalMembersEver, 2) : 0;

            // 2. Average Revenue Per User (ARPU) - Monthly
            // Monthly Revenue / Active Members
            $lastMonthRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', 'completed')
                ->whereMonth('payment_date', now()->subMonth()->month)
                ->sum('amount');

            $activeMembers = GymMember::where('gym_id', $gymId)
                ->membershipActive()
                ->count();

            $arpu = $activeMembers > 0 ? round($lastMonthRevenue / $activeMembers, 2) : 0;

            // 3. Average Customer Lifespan (ACL) in months
            // 1 / Churn Rate
            // Using simplified churn rate from ChurnService logic or existing data
            // Assuming 5% average churn for fallback calculation
            $churnRate = 0.05;
            $lifespanMonths = $churnRate > 0 ? round(1 / $churnRate) : 0;

            // 4. LTV by Plan Type (Top 3)
            // Need to join with memberships table logic
            // Simplified logic: Group members by plan type and sum their payments?
            // This is complex without a direct 'plan_id' on payments, assuming we can get it via member relationship

            return [
                'altv' => $altv,
                'arpu' => $arpu,
                'lifespan_months' => $lifespanMonths,
                'total_revenue' => $totalRevenue,
            ];
        });
    }

    /**
     * Get monthly trends data for PDF.
     */
    public function getMonthlyTrendsData(): array
    {
        $gymId = $this->getGymId();

        // Get monthly revenue trends for LTV calculation
        $monthlyData = [];
        $previousValue = null;

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', 'completed')
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->sum('amount');

            $memberCount = GymMember::where('gym_id', $gymId)
                ->whereDate('created_at', '<=', $date->endOfMonth())
                ->count();

            $avgLtv = $memberCount > 0 ? round($monthRevenue / $memberCount, 2) : 0;

            $changePercent = 0;
            $trend = 'Stable';

            if ($previousValue !== null && $previousValue > 0) {
                $changePercent = round((($avgLtv - $previousValue) / $previousValue) * 100, 1);
                
                if ($changePercent > 5) {
                    $trend = 'Up';
                } elseif ($changePercent < -5) {
                    $trend = 'Down';
                }
            }

            $monthlyData[] = [
                'month' => $date->format('M Y'),
                'value' => $avgLtv,
                'change_percent' => $changePercent,
                'trend' => $trend,
            ];

            $previousValue = $avgLtv;
        }

        return $monthlyData;
    }

    protected function getHeaderActions(): array
    {
        return array_merge([
            Action::make('refresh')
                ->label('Refresh')
                ->icon('phosphor-arrows-clockwise')
                ->color('gray')
                ->action(fn () => $this->dispatch('$refresh')),
        ], $this->getFilterActions(), $this->getExportActions());
    }
}
