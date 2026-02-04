<?php

namespace App\Services;

use App\Models\ScheduledReport;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportPdfGenerator
{
    public function generate(ScheduledReport $report): string
    {
        $reportClass = $report->report_type;

        // Instantiate page class to reuse logic
        try {
            $page = app($reportClass);
        } catch (\Throwable $e) {
            throw new \Exception("Could not instantiate report class: {$reportClass}");
        }

        // Set context manually
        if (property_exists($page, 'manualGymId')) {
            $page->manualGymId = $report->gym_id;
        }

        if (property_exists($page, 'filters')) {
            $page->filters = $report->filters ?? [];

            if (method_exists($page, 'mount')) {
                $page->mount();
                if ($report->filters) {
                    $page->filters = array_merge($page->filters, $report->filters);
                }
            }
        }

        $data = [];
        $viewName = 'pdf.reports.generic';
        $title = $report->name ?? 'Gym Report';

        if ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\ReportsDashboard::class) {
            $data = [
                'memberStats' => $page->getMemberStats(),
                'revenueStats' => $page->getRevenueStats(),
                'leadStats' => $page->getLeadStats(),
                'attendanceStats' => $page->getAttendanceStats(),
                'insights' => $page->getInsights(),
            ];
            $viewName = 'pdf.reports.dashboard';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\RevenueReports::class) {
            $data = [
                'stats' => [
                    'total_revenue' => $page->getSummaryStats()['total_revenue'] ?? 0,
                    'transaction_count' => $page->getSummaryStats()['transaction_count'] ?? 0,
                    'avg_transaction' => $page->getSummaryStats()['avg_transaction'] ?? 0,
                    'pending_payments' => $page->getSummaryStats()['pending_payments'] ?? 0,
                    'arpm' => $page->getSummaryStats()['arpm'] ?? 0,
                    'this_month' => $page->getSummaryStats()['this_month'] ?? 0,
                    'last_month' => $page->getSummaryStats()['last_month'] ?? 0,
                    'monthly_change' => $page->getSummaryStats()['monthly_change'] ?? 0,
                ],
                'pnl' => $page->getPnLData(),
            ];
            $viewName = 'pdf.reports.financial';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class) {
            $data = [
                'stats' => $page->getSummaryStats(),
                'growth' => method_exists($page, 'getGrowthData') ? $page->getGrowthData()->toArray() : [],
                'planDistribution' => method_exists($page, 'getPlanDistribution') ? $page->getPlanDistribution()->toArray() : [],
                'churnStats' => method_exists($page, 'getChurnStats') ? $page->getChurnStats() : [],
            ];
            $viewName = 'pdf.reports.member';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\LeadReports::class) {
            $data = [
                'stats' => $page->getSummaryStats(),
            ];
            $viewName = 'pdf.reports.generic';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\AttendanceReports::class) {
            $data = [
                'stats' => $page->getSummaryStats(),
            ];
            $viewName = 'pdf.reports.generic';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\TrainerReports::class) {
            $data = [
                'stats' => $page->getSummaryStats(),
            ];
            $viewName = 'pdf.reports.generic';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\EquipmentUsageReports::class) {
            $data = [
                'stats' => $page->getUsageStats(),
            ];
            $viewName = 'pdf.reports.generic';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\MemberLifetimeValueReports::class) {
            $data = [
                'stats' => $page->getLtvStats(),
            ];
            $viewName = 'pdf.reports.generic';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\ExpenseReportsCluster::class) {
            $data = [
                'stats' => $page->getSummaryStats(),
            ];
            $viewName = 'pdf.reports.generic';
        }

        // Create PDF
        $pdf = Pdf::loadView($viewName, [
            'report' => $report,
            'data' => $data,
            'gym' => $report->gym,
            'date' => now()->format('F j, Y'),
            'title' => $title,
        ]);

        return $pdf->output();
    }
}
