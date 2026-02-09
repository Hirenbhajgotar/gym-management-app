<?php

namespace Tests\Feature;

use App\Filament\Admin\Clusters\Reports\Pages\AttendanceReports;
use App\Filament\Admin\Clusters\Reports\Pages\ExpenseReportsCluster;
use App\Filament\Admin\Clusters\Reports\Pages\LeadReports;
use App\Filament\Admin\Clusters\Reports\Pages\TrainerReports;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPdfChartDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gym = Gym::factory()->create(['owner_id' => $this->user->id]);
    }

    public function test_lead_reports_has_chart_data_methods(): void
    {
        $page = new LeadReports;
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subMonths(6)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        $this->assertTrue(method_exists($page, 'getLeadFunnelData'));
        $this->assertTrue(method_exists($page, 'getConversionRatesData'));
        $this->assertTrue(method_exists($page, 'getLeadSourcesData'));
        $this->assertTrue(method_exists($page, 'getMonthlyTrendsData'));

        $this->assertIsArray($page->getLeadFunnelData());
        $this->assertIsArray($page->getConversionRatesData());
        $this->assertIsArray($page->getLeadSourcesData());
        $this->assertIsArray($page->getMonthlyTrendsData());
    }

    public function test_attendance_reports_has_chart_data_methods(): void
    {
        $page = new AttendanceReports;
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        $this->assertTrue(method_exists($page, 'getDailyPatternsData'));
        $this->assertTrue(method_exists($page, 'getWeeklyTrendsData'));
        $this->assertTrue(method_exists($page, 'getMonthlyTrendsData'));

        $this->assertIsArray($page->getDailyPatternsData());
        $this->assertIsArray($page->getWeeklyTrendsData());
        $this->assertIsArray($page->getMonthlyTrendsData());
    }

    public function test_trainer_reports_has_chart_data_methods(): void
    {
        $page = new TrainerReports;
        $page->manualGymId = $this->gym->id;

        $this->assertTrue(method_exists($page, 'getClientDistributionData'));
        $this->assertTrue(method_exists($page, 'getPerformanceMetricsData'));
        $this->assertTrue(method_exists($page, 'getMonthlyTrendsData'));

        $this->assertIsArray($page->getClientDistributionData());
        $this->assertIsArray($page->getPerformanceMetricsData());
        $this->assertIsArray($page->getMonthlyTrendsData());
    }

    public function test_expense_reports_has_chart_data_methods(): void
    {
        $page = new ExpenseReportsCluster;
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        $this->assertTrue(method_exists($page, 'getCategoryBreakdown'));
        $this->assertTrue(method_exists($page, 'getBudgetVarianceData'));
        $this->assertTrue(method_exists($page, 'getCostTrendsData'));
        $this->assertTrue(method_exists($page, 'getMonthlyTrendsData'));

        $this->assertIsArray($page->getBudgetVarianceData());
        $this->assertIsArray($page->getCostTrendsData());
        $this->assertIsArray($page->getMonthlyTrendsData());
    }
}
