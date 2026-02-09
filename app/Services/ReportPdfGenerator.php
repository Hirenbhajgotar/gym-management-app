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
                case 'lead_funnel':
                    if (method_exists($page, 'getLeadFunnelData')) {
                        $chartData['lead_funnel'] = $page->getLeadFunnelData();
                    } else {
                        $chartData['lead_funnel'] = [];
                    }
                    break;
                case 'conversion_rates':
                    if (method_exists($page, 'getConversionRatesData')) {
                        $chartData['conversion_rates'] = $page->getConversionRatesData();
                    } else {
                        $chartData['conversion_rates'] = [];
                    }
                    break;
                case 'lead_sources':
                    if (method_exists($page, 'getLeadSourcesData')) {
                        $chartData['lead_sources'] = $page->getLeadSourcesData();
                    } else {
                        $chartData['lead_sources'] = [];
                    }
                    break;
                case 'daily_patterns':
                    if (method_exists($page, 'getDailyPatternsData')) {
                        $chartData['daily_patterns'] = $page->getDailyPatternsData();
                    } else {
                        $chartData['daily_patterns'] = [];
                    }
                    break;
                case 'weekly_trends':
                    if (method_exists($page, 'getWeeklyTrendsData')) {
                        $chartData['weekly_trends'] = $page->getWeeklyTrendsData();
                    } else {
                        $chartData['weekly_trends'] = [];
                    }
                    break;
                case 'trainer_utilization':
                    if (method_exists($page, 'getTrainerDistribution')) {
                        $distribution = $page->getTrainerDistribution();
                        $chartData['trainer_utilization'] = is_array($distribution) ? $distribution : $distribution->toArray();
                    } else {
                        $chartData['trainer_utilization'] = [];
                    }
                    break;
                case 'client_distribution':
                    if (method_exists($page, 'getClientDistributionData')) {
                        $chartData['client_distribution'] = $page->getClientDistributionData();
                    } else {
                        $chartData['client_distribution'] = [];
                    }
                    break;
                case 'performance_metrics':
                    if (method_exists($page, 'getPerformanceMetricsData')) {
                        $chartData['performance_metrics'] = $page->getPerformanceMetricsData();
                    } else {
                        $chartData['performance_metrics'] = [];
                    }
                    break;
                case 'budget_variance':
                    if (method_exists($page, 'getBudgetVarianceData')) {
                        $chartData['budget_variance'] = $page->getBudgetVarianceData();
                    } else {
                        $chartData['budget_variance'] = [];
                    }
                    break;
                case 'cost_trends':
                    if (method_exists($page, 'getCostTrendsData')) {
                        $chartData['cost_trends'] = $page->getCostTrendsData();
                    } else {
                        $chartData['cost_trends'] = [];
                    }
                    break;
                case 'equipment_usage':
                    if (method_exists($page, 'getEquipmentUsageData')) {
                        $chartData['equipment_usage'] = $page->getEquipmentUsageData();
                    } else {
                        $chartData['equipment_usage'] = [];
                    }
                    break;
                case 'ltv_trends':
                    if (method_exists($page, 'getLtvTrendsData')) {
                        $chartData['ltv_trends'] = $page->getLtvTrendsData();
                    } else {
                        $chartData['ltv_trends'] = [];
                    }
                    break;
                case 'expense_categories':
                    if (method_exists($page, 'getCategoryBreakdown')) {
                        $chartData['expense_categories'] = $page->getCategoryBreakdown()->toArray();
                    } elseif (property_exists($page, 'categoryBreakdown')) {
                        $chartData['expense_categories'] = $page->categoryBreakdown->toArray();
                    } else {
                        $chartData['expense_categories'] = [];
                    }
                    break;
                default:
                    // Return empty array for unknown chart types
                    $chartData[$chartType] = [];
                    break;
            }
        }

        return $chartData;
    }

    /**
     * Get monthly trends data from the actual page data
     */
    private function getMonthlyTrends($page, string $reportType): array
    {
        // Check if page has a method to provide monthly trends
        if (method_exists($page, 'getMonthlyTrendsData')) {
            return $page->getMonthlyTrendsData();
        }

        // Return empty array if no data available
        return [];
    }
}
