<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Admin\Clusters\Reports\Pages\AttendanceReports;
use App\Filament\Admin\Clusters\Reports\Pages\LeadReports;
use App\Filament\Admin\Clusters\Reports\Pages\MemberReports;
use App\Filament\Admin\Clusters\Reports\Pages\ReportsDashboard;
use App\Filament\Admin\Clusters\Reports\Pages\RevenueReports;
use App\Traits\HasTenantAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportApiController extends Controller
{
    use HasTenantAccess;

    public function members(Request $request): JsonResponse
    {
        return $this->generateReport($request, MemberReports::class, fn ($report) => [
            'stats' => $report->getSummaryStats(),
            'growth' => method_exists($report, 'getGrowthData') ? $report->getGrowthData()->toArray() : [],
            'plan_distribution' => method_exists($report, 'getPlanDistribution') ? $report->getPlanDistribution()->toArray() : [],
            'churn_stats' => method_exists($report, 'getChurnStats') ? $report->getChurnStats() : [],
        ]);
    }

    public function revenue(Request $request): JsonResponse
    {
        return $this->generateReport($request, RevenueReports::class, fn ($report) => [
            'stats' => $report->getSummaryStats(),
            'pnl' => method_exists($report, 'getPnLData') ? $report->getPnLData() : [],
            'revenue_breakdown' => method_exists($report, 'getRevenueBreakdown') ? $report->getRevenueBreakdown() : [],
            'expense_breakdown' => method_exists($report, 'getExpenseBreakdown') ? $report->getExpenseBreakdown() : [],
        ]);
    }

    public function leads(Request $request): JsonResponse
    {
        return $this->generateReport($request, LeadReports::class, fn ($report) => [
            'stats' => $report->getSummaryStats(),
            'lead_funnel' => method_exists($report, 'getLeadFunnelData') ? $report->getLeadFunnelData() : [],
            'conversion_rates' => method_exists($report, 'getConversionRatesData') ? $report->getConversionRatesData() : [],
            'lead_sources' => method_exists($report, 'getLeadSourcesData') ? $report->getLeadSourcesData() : [],
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        return $this->generateReport($request, AttendanceReports::class, fn ($report) => [
            'stats' => $report->getSummaryStats(),
            'hourly_distribution' => method_exists($report, 'getHourlyDistribution') ? $report->getHourlyDistribution() : [],
            'daily_patterns' => method_exists($report, 'getDailyPatternsData') ? $report->getDailyPatternsData() : [],
            'weekly_trends' => method_exists($report, 'getWeeklyTrendsData') ? $report->getWeeklyTrendsData() : [],
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        return $this->generateReport($request, ReportsDashboard::class, fn ($dashboard) => [
            'member_stats' => method_exists($dashboard, 'getMemberStats') ? $dashboard->getMemberStats() : [],
            'revenue_stats' => method_exists($dashboard, 'getRevenueStats') ? $dashboard->getRevenueStats() : [],
            'lead_stats' => method_exists($dashboard, 'getLeadStats') ? $dashboard->getLeadStats() : [],
            'attendance_stats' => method_exists($dashboard, 'getAttendanceStats') ? $dashboard->getAttendanceStats() : [],
            'insights' => method_exists($dashboard, 'getInsights') ? $dashboard->getInsights() : [],
        ]);
    }

    private function generateReport(Request $request, string $reportClass, callable $getData): JsonResponse
    {
        $gymId = $this->getGymId();

        $report = new $reportClass;
        $report->manualGymId = $gymId;
        $report->filters = $request->only(['date_range', 'start_date', 'end_date']);
        $report->mount();

        $data = $getData($report);

        $data['filters'] = $report->filters;
        $data['generated_at'] = now()->toISOString();

        return response()->json($data);
    }
}
