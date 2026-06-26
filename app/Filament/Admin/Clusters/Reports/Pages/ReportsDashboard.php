<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Collection;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Clusters\Reports\Widgets\CohortRetentionChart;
use App\Filament\Admin\Clusters\Reports\Widgets\GoalProgressWidget;
use App\Filament\Admin\Clusters\Reports\Widgets\GymHealthScoreWidget;
use App\Filament\Admin\Clusters\Reports\Widgets\RealtimeAttendanceWidget;
use App\Filament\BasePages\BasePage;
use App\Interfaces\ShareableReport;
use App\Models\Attendances;
use App\Models\GymMember;
use App\Models\Lead;
use App\Models\MemberPayment;
use App\Models\Trainer;
use App\Services\InsightsService;
use App\Services\ReportCacheService;
use App\Traits\HasReportFilters;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Cache;

class ReportsDashboard extends BasePage implements ShareableReport
{
    use HasReportFilters;

    protected static ?string $navigationIcon = 'phosphor-chart-line';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Reports Dashboard';

    protected static ?int $navigationSort = 0;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.reports-dashboard';

    /**
     * Get Livewire listeners for real-time updates via Echo.
     * Note: Event names must be prefixed with a dot (.) when using broadcastAs()
     */
    protected function getListeners(): array
    {
        $gymId = $this->getGymId();

        return [
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.RevenueStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.LeadStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.AttendanceStatsUpdated" => '$refresh',
        ];
    }

    /**
     * Get member overview stats with sparkline data.
     */
    public function getMemberStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = ReportCacheService::MEMBER_STATS . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $totalMembers = GymMember::where('gym_id', $gymId)->count();
            $activeMembers = GymMember::where('gym_id', $gymId)->membershipActive()->count();
            $expiredMembers = GymMember::where('gym_id', $gymId)->membershipExpired()->count();

            // New members this month vs last month
            $newThisMonth = GymMember::where('gym_id', $gymId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $newLastMonth = GymMember::where('gym_id', $gymId)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->count();

            $growthRate = $this->calculatePercentageChange($newThisMonth, $newLastMonth);

            // Sparkline data for last 30 days
            $sparkline = $this->getSparklineData('gym_members', 'created_at', 'count', null, 30);

            return [
                'total' => $totalMembers,
                'active' => $activeMembers,
                'expired' => $expiredMembers,
                'new_this_month' => $newThisMonth,
                'growth_rate' => $growthRate,
                'growth_color' => $this->getTrendColor($growthRate),
                'sparkline' => $sparkline,
                'alert' => $this->checkMemberThreshold($growthRate, $totalMembers),
            ];
        });
    }

    /**
     * Get revenue overview stats with sparkline data.
     */
    public function getRevenueStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = ReportCacheService::REVENUE_STATS . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            // MTD Revenue
            $mtdRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount');

            // Last month's total revenue
            $lastMonthRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereMonth('payment_date', now()->subMonth()->month)
                ->whereYear('payment_date', now()->subMonth()->year)
                ->sum('amount');

            // YTD Revenue
            $ytdRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereYear('payment_date', now()->year)
                ->sum('amount');

            $revenueChange = $this->calculatePercentageChange($mtdRevenue, $lastMonthRevenue);

            // Today's collection
            $todayCollection = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereDate('payment_date', today())
                ->sum('amount');

            // Sparkline data for last 30 days
            $sparkline = $this->getSparklineData(
                'member_payments',
                'payment_date',
                'sum',
                'amount',
                30,
                ['status' => PaymentStatus::COMPLETED->value]
            );

            return [
                'mtd' => $mtdRevenue,
                'ytd' => $ytdRevenue,
                'last_month' => $lastMonthRevenue,
                'today' => $todayCollection,
                'change_rate' => $revenueChange,
                'change_color' => $this->getTrendColor($revenueChange),
                'sparkline' => $sparkline,
            ];
        });
    }

    /**
     * Get lead pipeline stats with sparkline data.
     */
    public function getLeadStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = ReportCacheService::LEAD_STATS . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $totalLeads = Lead::where('gym_id', $gymId)->count();
            $newLeads = Lead::where('gym_id', $gymId)->where('status', LeadStatus::NEW)->count();
            $activeLeads = Lead::where('gym_id', $gymId)
                ->whereNotIn('status', [LeadStatus::CONVERTED, LeadStatus::LOST])
                ->count();

            // Conversion rate (this month)
            $convertedThisMonth = Lead::where('gym_id', $gymId)
                ->where('status', LeadStatus::CONVERTED)
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count();

            $totalThisMonth = Lead::where('gym_id', $gymId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $conversionRate = $totalThisMonth > 0
                ? round(($convertedThisMonth / $totalThisMonth) * 100, 1)
                : 0;

            // Sparkline data for last 30 days
            $sparkline = $this->getSparklineData('leads', 'created_at', 'count', null, 30);

            return [
                'total' => $totalLeads,
                'new' => $newLeads,
                'active' => $activeLeads,
                'converted_this_month' => $convertedThisMonth,
                'conversion_rate' => $conversionRate,
                'sparkline' => $sparkline,
            ];
        });
    }

    /**
     * Get trainer utilization stats.
     */
    public function getTrainerStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = ReportCacheService::TRAINER_STATS . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $totalTrainers = Trainer::where('gym_id', $gymId)->count();

            $averageClients = $totalTrainers > 0
                ? round(GymMember::where('gym_id', $gymId)->whereNotNull('trainer_id')->count() / $totalTrainers, 1)
                : 0;

            $trainersWithClients = Trainer::where('gym_id', $gymId)
                ->whereHas('members')
                ->count();

            $utilizationRate = $totalTrainers > 0
                ? round(($trainersWithClients / $totalTrainers) * 100, 1)
                : 0;

            return [
                'total' => $totalTrainers,
                'average_clients' => $averageClients,
                'with_clients' => $trainersWithClients,
                'utilization_rate' => $utilizationRate,
            ];
        });
    }

    /**
     * Get attendance stats for today with sparkline data.
     */
    public function getAttendanceStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = ReportCacheService::ATTENDANCE_STATS . $gymId . ':' . today()->format('Y-m-d');

        return Cache::flexible($cacheKey, [now()->addMinutes(5), now()->addMinutes(30)], function () use ($gymId) {
            // Today's check-ins
            $todayCheckIns = Attendances::where('gym_id', $gymId)
                ->whereDate('check_in_time', today())
                ->count();

            // Currently checked in
            $currentlyIn = Attendances::where('gym_id', $gymId)
                ->whereDate('check_in_time', today())
                ->where('status', 'checked_in')
                ->count();

            // This week's average
            $weekStart = now()->startOfWeek();
            $daysInWeek = now()->diffInDays($weekStart) + 1;
            $weeklyTotal = Attendances::where('gym_id', $gymId)
                ->whereBetween('check_in_time', [$weekStart, now()])
                ->count();

            $weeklyAverage = $daysInWeek > 0 ? round($weeklyTotal / $daysInWeek, 0) : 0;

            // Peak hour (most common check-in hour today)
            $peakHour = Attendances::where('gym_id', $gymId)
                ->whereDate('check_in_time', today())
                ->selectRaw('HOUR(check_in_time) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderByDesc('count')
                ->first();

            // Sparkline data for last 30 days
            $sparkline = $this->getSparklineData('attendances', 'check_in_time', 'count', null, 30);

            return [
                'today' => $todayCheckIns,
                'currently_in' => $currentlyIn,
                'weekly_average' => $weeklyAverage,
                'peak_hour' => $peakHour?->hour ? Date::createFromTime($peakHour->hour)->format('g A') : 'N/A',
                'sparkline' => $sparkline,
            ];
        });
    }

    /**
     * Get footer widgets for the dashboard.
     */
    protected function getFooterWidgets(): array
    {
        return [
            RealtimeAttendanceWidget::class,
            GymHealthScoreWidget::class,
            GoalProgressWidget::class,
            CohortRetentionChart::class,
        ];
    }

    /**
     * Get footer widgets column count.
     */
    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * Get actionable insights for the gym.
     */
    public function getInsights(): Collection
    {
        $gymId = $this->getGymId();
        // Cache for 2 hours
        $cacheKey = ReportCacheService::INSIGHTS . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $service = resolve(InsightsService::class);

            return $service->getDailyInsights($gymId);
        });
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

    /**
     * Check member growth threshold.
     */
    protected function checkMemberThreshold(float $growthRate, int $totalMembers): ?array
    {
        if ($growthRate < -10) {
            return [
                'type' => 'danger',
                'message' => 'Member growth declined by more than 10%',
            ];
        }

        if ($totalMembers < 10) {
            return [
                'type' => 'warning',
                'message' => 'Low member count - Focus on lead conversion',
            ];
        }

        return null;
    }

    /**
     * Check revenue threshold.
     */
    protected function checkRevenueThreshold(float $changeRate, float $mtd): ?array
    {
        if ($changeRate < -15) {
            return [
                'type' => 'danger',
                'message' => 'Revenue dropped by more than 15%',
            ];
        }

        if ($mtd < 10000) {
            return [
                'type' => 'warning',
                'message' => 'MTD revenue below target',
            ];
        }

        return null;
    }

    /**
     * Check lead conversion threshold.
     */
    protected function checkLeadThreshold(float $conversionRate): ?array
    {
        if ($conversionRate < 10) {
            return [
                'type' => 'danger',
                'message' => 'Conversion rate below 10% - Review lead quality',
            ];
        }

        if ($conversionRate < 20) {
            return [
                'type' => 'warning',
                'message' => 'Conversion rate could be improved',
            ];
        }

        return null;
    }

    /**
     * Check attendance threshold.
     */
    protected function checkAttendanceThreshold(float $weeklyAverage): ?array
    {
        if ($weeklyAverage < 20) {
            return [
                'type' => 'warning',
                'message' => 'Low average attendance - Engage members',
            ];
        }

        return null;
    }

    /**
     * Get shared report data.
     */
    public function getSharedReportData(): array
    {
        $memberStats = $this->getMemberStats();
        $revenueStats = $this->getRevenueStats();
        $leadStats = $this->getLeadStats();
        $attendanceStats = $this->getAttendanceStats();

        return [
            'stats' => [
                'total_members' => $memberStats['total'] ?? 0,
                'revenue_this_month' => $revenueStats['mtd'] ?? 0,
                'new_leads' => $leadStats['new'] ?? 0,
                'check_ins_today' => $attendanceStats['today'] ?? 0,
            ],
            'memberStats' => $memberStats,
            'revenueStats' => $revenueStats,
            'leadStats' => $leadStats,
            'attendanceStats' => $attendanceStats,
            'insights' => $this->getInsights(),
            'chart_data' => [
                'active_members' => $memberStats['sparkline'] ?? [],
                'revenue_trend' => $revenueStats['sparkline'] ?? [],
                'lead_growth' => $leadStats['sparkline'] ?? [],
                'attendance' => $attendanceStats['sparkline'] ?? [],
            ],
        ];
    }

    /**
     * Get shared report title.
     */
    public function getSharedReportTitle(): string
    {
        return 'Dashboard Report';
    }
}
