<?php

namespace App\Http\Controllers;

use App\Filament\Admin\Clusters\Reports\Pages\AttendanceReports;
use App\Filament\Admin\Clusters\Reports\Pages\LeadReports;
use App\Filament\Admin\Clusters\Reports\Pages\MemberReports;
use App\Filament\Admin\Clusters\Reports\Pages\RevenueReports;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportApiController extends Controller
{
    use \App\Traits\HasTenantAccess;

    public function members(Request $request): JsonResponse
    {
        $gymId = $this->getGymId();
        // $gym = Gym::findOrFail($gymId); // getGymId() ensures it exists or throws if not found/set? actually getGym() from trait gets it from Filament context.
        // We can keep $gym = Gym::findOrFail($gymId); if we want to be sure, but getGym() already returns a Gym model.
        // Let's just get the ID as that's what is used.

        $report = new MemberReports;
        $report->manualGymId = $gymId;
        $report->filters = $request->only(['date_range', 'start_date', 'end_date']);
        $report->mount();

        return response()->json([
            'stats' => $report->getSummaryStats(),
            'growth' => method_exists($report, 'getGrowthData') ? $report->getGrowthData()->toArray() : [],
            'plan_distribution' => method_exists($report, 'getPlanDistribution') ? $report->getPlanDistribution()->toArray() : [],
            'churn_stats' => method_exists($report, 'getChurnStats') ? $report->getChurnStats() : [],
            'filters' => $report->filters,
            'generated_at' => now()->toISOString(),
        ]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $gymId = $this->getGymId();
        // $gym = Gym::findOrFail($gymId); // getGymId() ensures it exists or throws if not found/set? actually getGym() from trait gets it from Filament context.
        // We can keep $gym = Gym::findOrFail($gymId); if we want to be sure, but getGym() already returns a Gym model.
        // Let's just get the ID as that's what is used.

        $report = new RevenueReports;
        $report->manualGymId = $gymId;
        $report->filters = $request->only(['date_range', 'start_date', 'end_date']);
        $report->mount();

        return response()->json([
            'stats' => $report->getSummaryStats(),
            'pnl' => method_exists($report, 'getPnLData') ? $report->getPnLData() : [],
            'revenue_breakdown' => method_exists($report, 'getRevenueBreakdown') ? $report->getRevenueBreakdown() : [],
            'expense_breakdown' => method_exists($report, 'getExpenseBreakdown') ? $report->getExpenseBreakdown() : [],
            'filters' => $report->filters,
            'generated_at' => now()->toISOString(),
        ]);
    }

    public function leads(Request $request): JsonResponse
    {
        $gymId = $this->getGymId();
        // $gym = Gym::findOrFail($gymId); // getGymId() ensures it exists or throws if not found/set? actually getGym() from trait gets it from Filament context.
        // We can keep $gym = Gym::findOrFail($gymId); if we want to be sure, but getGym() already returns a Gym model.
        // Let's just get the ID as that's what is used.

        $report = new LeadReports;
        $report->manualGymId = $gymId;
        $report->filters = $request->only(['date_range', 'start_date', 'end_date']);
        $report->mount();

        return response()->json([
            'stats' => $report->getSummaryStats(),
            'lead_funnel' => method_exists($report, 'getLeadFunnelData') ? $report->getLeadFunnelData() : [],
            'conversion_rates' => method_exists($report, 'getConversionRatesData') ? $report->getConversionRatesData() : [],
            'lead_sources' => method_exists($report, 'getLeadSourcesData') ? $report->getLeadSourcesData() : [],
            'filters' => $report->filters,
            'generated_at' => now()->toISOString(),
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $gymId = $this->getGymId();
        // $gym = Gym::findOrFail($gymId); // getGymId() ensures it exists or throws if not found/set? actually getGym() from trait gets it from Filament context.
        // We can keep $gym = Gym::findOrFail($gymId); if we want to be sure, but getGym() already returns a Gym model.
        // Let's just get the ID as that's what is used.

        $report = new AttendanceReports;
        $report->manualGymId = $gymId;
        $report->filters = $request->only(['date_range', 'start_date', 'end_date']);
        $report->mount();

        return response()->json([
            'stats' => $report->getSummaryStats(),
            'hourly_distribution' => method_exists($report, 'getHourlyDistribution') ? $report->getHourlyDistribution() : [],
            'daily_patterns' => method_exists($report, 'getDailyPatternsData') ? $report->getDailyPatternsData() : [],
            'weekly_trends' => method_exists($report, 'getWeeklyTrendsData') ? $report->getWeeklyTrendsData() : [],
            'filters' => $report->filters,
            'generated_at' => now()->toISOString(),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $gymId = $this->getGymId();
        // $gym = Gym::findOrFail($gymId); // getGymId() ensures it exists or throws if not found/set? actually getGym() from trait gets it from Filament context.
        // We can keep $gym = Gym::findOrFail($gymId); if we want to be sure, but getGym() already returns a Gym model.
        // Let's just get the ID as that's what is used.

        $dashboard = new \App\Filament\Admin\Clusters\Reports\Pages\ReportsDashboard;
        $dashboard->manualGymId = $gymId;
        $dashboard->filters = $request->only(['date_range', 'start_date', 'end_date']);
        $dashboard->mount();

        return response()->json([
            'member_stats' => method_exists($dashboard, 'getMemberStats') ? $dashboard->getMemberStats() : [],
            'revenue_stats' => method_exists($dashboard, 'getRevenueStats') ? $dashboard->getRevenueStats() : [],
            'lead_stats' => method_exists($dashboard, 'getLeadStats') ? $dashboard->getLeadStats() : [],
            'attendance_stats' => method_exists($dashboard, 'getAttendanceStats') ? $dashboard->getAttendanceStats() : [],
            'insights' => method_exists($dashboard, 'getInsights') ? $dashboard->getInsights() : [],
            'filters' => $dashboard->filters,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
