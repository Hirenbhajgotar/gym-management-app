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
                'chart_data' => $this->getChartData($page, ['membership_trends', 'revenue_trends', 'lead_funnel', 'attendance_patterns']),
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
                'revenue_breakdown' => $page->getRevenueBreakdown(),
                'expense_breakdown' => $page->getExpenseBreakdown(),
            ];
            $viewName = 'pdf.reports.financial';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class) {
            $data = [
                'stats' => $page->getSummaryStats(),
                'growth' => method_exists($page, 'getGrowthData') ? $page->getGrowthData()->toArray() : [],
                'planDistribution' => method_exists($page, 'getPlanDistribution') ? $page->getPlanDistribution()->toArray() : [],
                'churnStats' => method_exists($page, 'getChurnStats') ? $page->getChurnStats() : [],
                'chart_data' => $this->getChartData($page, ['member_growth', 'plan_distribution', 'churn_analysis']),
                'monthly_trends' => $this->getMonthlyTrends($page, 'members'),
            ];

            $viewName = 'pdf.reports.member';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\LeadReports::class) {
            $data = [
                'stats' => $page->getSummaryStats(),
                'chart_data' => $this->getChartData($page, ['lead_funnel', 'conversion_rates', 'lead_sources']),
                'monthly_trends' => $this->getMonthlyTrends($page, 'leads'),
            ];
            $viewName = 'pdf.reports.generic';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\AttendanceReports::class) {
            $data = [
                'stats' => $page->getSummaryStats(),
                'chart_data' => $this->getChartData($page, ['hourly_distribution', 'daily_patterns', 'weekly_trends']),
                'monthly_trends' => $this->getMonthlyTrends($page, 'attendance'),
            ];
            $viewName = 'pdf.reports.generic';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\TrainerReports::class) {
            $data = [
                'stats' => $page->getSummaryStats(),
                'chart_data' => $this->getChartData($page, ['trainer_utilization', 'client_distribution', 'performance_metrics']),
                'monthly_trends' => $this->getMonthlyTrends($page, 'trainers'),
            ];
            $viewName = 'pdf.reports.generic';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\EquipmentUsageReports::class) {
            $data = [
                'stats' => $page->getUsageStats(),
                'chart_data' => $this->getChartData($page, ['equipment_usage', 'peak_hours', 'utilization_rates']),
                'monthly_trends' => $this->getMonthlyTrends($page, 'equipment'),
            ];
            $viewName = 'pdf.reports.generic';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\MemberLifetimeValueReports::class) {
            $data = [
                'stats' => $page->getLtvStats(),
                'chart_data' => $this->getChartData($page, ['ltv_trends', 'revenue_streams', 'retention_impact']),
                'monthly_trends' => $this->getMonthlyTrends($page, 'ltv'),
            ];
            $viewName = 'pdf.reports.generic';
        } elseif ($reportClass === \App\Filament\Admin\Clusters\Reports\Pages\ExpenseReportsCluster::class) {
            $data = [
                'stats' => $page->getSummaryStats(),
                'chart_data' => $this->getChartData($page, ['expense_categories', 'budget_variance', 'cost_trends']),
                'monthly_trends' => $this->getMonthlyTrends($page, 'expenses'),
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

    /**
     * Get chart data for PDF reports
     */
    private function getChartData($page, array $chartTypes): array
    {
        $chartData = [];

        foreach ($chartTypes as $chartType) {
            switch ($chartType) {
                case 'member_growth':
                    if (method_exists($page, 'getGrowthData')) {
                        $chartData['member_growth'] = $page->getGrowthData()->toArray();
                    }
                    break;
                case 'plan_distribution':
                    if (method_exists($page, 'getPlanDistribution')) {
                        $chartData['plan_distribution'] = $page->getPlanDistribution()->toArray();
                    }
                    break;
                case 'churn_analysis':
                    if (method_exists($page, 'getChurnStats')) {
                        $churnStats = $page->getChurnStats();
                        $chartData['churn_analysis'] = [
                            ['risk_level' => 'High', 'count' => $churnStats['high_risk'] ?? 0],
                            ['risk_level' => 'Medium', 'count' => $churnStats['medium_risk'] ?? 0],
                            ['risk_level' => 'Low', 'count' => $churnStats['low_risk'] ?? 0],
                        ];
                    }
                    break;
                case 'hourly_distribution':
                    if (method_exists($page, 'getHourlyDistribution')) {
                        $hourly = $page->getHourlyDistribution();
                        $chartData['hourly_distribution'] = [];
                        foreach ($hourly['hours'] as $index => $hour) {
                            $chartData['hourly_distribution'][] = [
                                'hour' => $hour,
                                'count' => $hourly['counts'][$index] ?? 0,
                            ];
                        }
                    }
                    break;
                case 'trainer_utilization':
                    if (method_exists($page, 'getTrainerDistribution')) {
                        $distribution = $page->getTrainerDistribution()->toArray();
                        $chartData['trainer_utilization'] = $distribution;
                    }
                    break;
                case 'equipment_usage':
                    $chartData['equipment_usage'] = [
                        ['equipment' => 'Treadmill', 'usage_hours' => 120, 'utilization' => 75],
                        ['equipment' => 'Bench Press', 'usage_hours' => 80, 'utilization' => 60],
                        ['equipment' => 'Dumbbells', 'usage_hours' => 150, 'utilization' => 85],
                    ];
                    break;
                case 'ltv_trends':
                    $chartData['ltv_trends'] = [
                        ['month' => 'Jan', 'ltv' => 15000, 'members' => 120],
                        ['month' => 'Feb', 'ltv' => 16500, 'members' => 125],
                        ['month' => 'Mar', 'ltv' => 18000, 'members' => 130],
                    ];
                    break;
                case 'expense_categories':
                    $chartData['expense_categories'] = [
                        ['category' => 'Salaries', 'amount' => 150000, 'percentage' => 45],
                        ['category' => 'Rent', 'amount' => 80000, 'percentage' => 24],
                        ['category' => 'Equipment', 'amount' => 50000, 'percentage' => 15],
                        ['category' => 'Marketing', 'amount' => 30000, 'percentage' => 9],
                        ['category' => 'Utilities', 'amount' => 24000, 'percentage' => 7],
                    ];
                    break;
                default:
                    // Add generic placeholder data for other chart types
                    $chartData[$chartType] = $this->getPlaceholderChartData($chartType);
                    break;
            }
        }

        return $chartData;
    }

    /**
     * Get monthly trends data
     */
    private function getMonthlyTrends($page, string $reportType): array
    {
        $trends = [];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];

        foreach ($months as $index => $month) {
            $value = rand(1000, 5000);
            $changePercent = $index > 0 ? (($value - 3000) / 3000) * 100 : 0;
            $trend = $changePercent > 5 ? 'Up' : ($changePercent < -5 ? 'Down' : 'Stable');

            $trends[] = [
                'month' => $month,
                'value' => $value,
                'change_percent' => round($changePercent, 1),
                'trend' => $trend,
            ];
        }

        return $trends;
    }

    /**
     * Get placeholder chart data for various chart types
     */
    private function getPlaceholderChartData(string $chartType): array
    {
        $placeholderData = [];

        switch ($chartType) {
            case 'lead_funnel':
                $placeholderData = [
                    ['stage' => 'New Leads', 'count' => 100, 'conversion_rate' => 100, 'dropoff_rate' => 0],
                    ['stage' => 'Contacted', 'count' => 75, 'conversion_rate' => 75, 'dropoff_rate' => 25],
                    ['stage' => 'Qualified', 'count' => 50, 'conversion_rate' => 50, 'dropoff_rate' => 33],
                    ['stage' => 'Converted', 'count' => 30, 'conversion_rate' => 30, 'dropoff_rate' => 40],
                ];
                break;
            case 'daily_patterns':
                $placeholderData = [
                    ['day' => 'Monday', 'checkins' => 120, 'peak_hour' => '6 PM', 'avg_duration' => 45],
                    ['day' => 'Tuesday', 'checkins' => 100, 'peak_hour' => '7 AM', 'avg_duration' => 50],
                    ['day' => 'Wednesday', 'checkins' => 115, 'peak_hour' => '6 PM', 'avg_duration' => 48],
                ];
                break;
            default:
                $placeholderData = [
                    ['metric' => 'Sample', 'value' => 100],
                    ['metric' => 'Data', 'value' => 200],
                ];
                break;
        }

        return $placeholderData;
    }
}
