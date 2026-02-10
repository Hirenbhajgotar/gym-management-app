<?php

namespace App\Http\Controllers;

use App\Models\ShareableReportLink;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SharedReportController extends Controller
{
    public function show(string $token): View|Response
    {
        $link = ShareableReportLink::with(['gym', 'user'])
            ->where('token', $token)
            ->valid()
            ->first();

        if (! $link) {
            abort(404, 'Shared report not found or has expired');
        }

        // Get the report class
        $reportClass = $link->report_type;

        try {
            $page = app($reportClass);
        } catch (\Throwable $e) {
            abort(404, 'Report type not found');
        }

        // Set context for the report
        if (property_exists($page, 'manualGymId')) {
            $page->manualGymId = $link->gym_id;
        }

        if (property_exists($page, 'filters')) {
            $page->filters = $link->filters ?? [];

            if (method_exists($page, 'mount')) {
                $page->mount();
                if ($link->filters) {
                    $page->filters = array_merge($page->filters, $link->filters);
                }
            }
        }

        // Prepare data for the view
        $data = $this->getReportData($page, $reportClass);

        return view('shared-reports.show', [
            'link' => $link,
            'report' => $page,
            'data' => $data,
            'title' => $this->getReportTitle($reportClass),
            'generatedAt' => now(),
        ]);
    }

    private function getReportData($page, string $reportClass): array
    {
        $data = [];

        switch ($reportClass) {
            case \App\Filament\Admin\Clusters\Reports\Pages\ReportsDashboard::class:
                $data = [
                    'memberStats' => method_exists($page, 'getMemberStats') ? $page->getMemberStats() : [],
                    'revenueStats' => method_exists($page, 'getRevenueStats') ? $page->getRevenueStats() : [],
                    'leadStats' => method_exists($page, 'getLeadStats') ? $page->getLeadStats() : [],
                    'attendanceStats' => method_exists($page, 'getAttendanceStats') ? $page->getAttendanceStats() : [],
                    'insights' => method_exists($page, 'getInsights') ? $page->getInsights() : [],
                ];
                break;

            case \App\Filament\Admin\Clusters\Reports\Pages\RevenueReports::class:
                $data = [
                    'stats' => method_exists($page, 'getSummaryStats') ? $page->getSummaryStats() : [],
                    'pnl' => method_exists($page, 'getPnLData') ? $page->getPnLData() : [],
                    'revenue_breakdown' => method_exists($page, 'getRevenueBreakdown') ? $page->getRevenueBreakdown() : [],
                    'expense_breakdown' => method_exists($page, 'getExpenseBreakdown') ? $page->getExpenseBreakdown() : [],
                ];
                break;

            case \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class:
                $data = [
                    'stats' => method_exists($page, 'getSummaryStats') ? $page->getSummaryStats() : [],
                    'growth' => method_exists($page, 'getGrowthData') ? $page->getGrowthData()->toArray() : [],
                    'planDistribution' => method_exists($page, 'getPlanDistribution') ? $page->getPlanDistribution()->toArray() : [],
                    'churnStats' => method_exists($page, 'getChurnStats') ? $page->getChurnStats() : [],
                ];
                break;

            case \App\Filament\Admin\Clusters\Reports\Pages\LeadReports::class:
                $data = [
                    'stats' => method_exists($page, 'getSummaryStats') ? $page->getSummaryStats() : [],
                ];
                break;

            case \App\Filament\Admin\Clusters\Reports\Pages\AttendanceReports::class:
                $data = [
                    'stats' => method_exists($page, 'getSummaryStats') ? $page->getSummaryStats() : [],
                ];
                break;

            case \App\Filament\Admin\Clusters\Reports\Pages\TrainerReports::class:
                $data = [
                    'stats' => method_exists($page, 'getSummaryStats') ? $page->getSummaryStats() : [],
                ];
                break;

            case \App\Filament\Admin\Clusters\Reports\Pages\EquipmentUsageReports::class:
                $data = [
                    'stats' => method_exists($page, 'getUsageStats') ? $page->getUsageStats() : [],
                ];
                break;

            case \App\Filament\Admin\Clusters\Reports\Pages\MemberLifetimeValueReports::class:
                $data = [
                    'stats' => method_exists($page, 'getLtvStats') ? $page->getLtvStats() : [],
                ];
                break;

            case \App\Filament\Admin\Clusters\Reports\Pages\ExpenseReportsCluster::class:
                $data = [
                    'stats' => method_exists($page, 'getSummaryStats') ? $page->getSummaryStats() : [],
                ];
                break;

            default:
                $data = ['stats' => []];
                break;
        }

        return $data;
    }

    private function getReportTitle(string $reportClass): string
    {
        $titles = [
            \App\Filament\Admin\Clusters\Reports\Pages\ReportsDashboard::class => 'Dashboard Report',
            \App\Filament\Admin\Clusters\Reports\Pages\RevenueReports::class => 'Revenue Report',
            \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class => 'Member Report',
            \App\Filament\Admin\Clusters\Reports\Pages\LeadReports::class => 'Lead Report',
            \App\Filament\Admin\Clusters\Reports\Pages\AttendanceReports::class => 'Attendance Report',
            \App\Filament\Admin\Clusters\Reports\Pages\TrainerReports::class => 'Trainer Report',
            \App\Filament\Admin\Clusters\Reports\Pages\EquipmentUsageReports::class => 'Equipment Usage Report',
            \App\Filament\Admin\Clusters\Reports\Pages\MemberLifetimeValueReports::class => 'Member Lifetime Value Report',
            \App\Filament\Admin\Clusters\Reports\Pages\ExpenseReportsCluster::class => 'Expense Report',
        ];

        return $titles[$reportClass] ?? 'Report';
    }
}
