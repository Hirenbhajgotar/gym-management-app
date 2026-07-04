<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\DefaultRole;
use App\Exports\TrainerReportExport;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Clusters\Reports\Widgets\TrainerClientChart;
use App\Filament\Admin\Clusters\Reports\Widgets\TrainerUtilizationWidget;
use App\Filament\BasePages\BasePage;
use App\Interfaces\ShareableReport;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Trainer;
use App\Services\ReportCacheService;
use App\Traits\HasReportFilters;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class TrainerReports extends BasePage implements ShareableReport
{
    use HasReportFilters;

    protected static ?string $navigationIcon = 'phosphor-barbell';

    protected static ?string $navigationLabel = 'Trainer Reports';

    protected static ?string $title = 'Trainer Analytics';

    protected static ?int $navigationSort = 4;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.trainer-reports';

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['page'],
            DefaultRole::RECEPTIONIST->value => ['page'],
            DefaultRole::TRAINER->value => [],
        ];
    }

    /**
     * Get Livewire listeners for real-time updates via Echo.
     */
    protected function getListeners(): array
    {
        $gymId = $this->getGymId();

        return [
            "echo-private:reports.{$gymId},.TrainerStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => '$refresh',
        ];
    }

    /**
     * Get summary statistics.
     */
    public function getSummaryStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = ReportCacheService::TRAINER_SUMMARY . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $totalTrainers = Trainer::query()->where('gym_id', $gymId)->count();
            $activeTrainers = Trainer::query()->where('gym_id', $gymId)->where('is_available', true)->count();

            // Total members with trainers
            $membersWithTrainer = GymMember::query()->where('gym_id', $gymId)->membershipActive()->whereNotNull('trainer_id')->count();
            $totalActiveMembers = GymMember::query()->where('gym_id', $gymId)->membershipActive()->count();

            // Average clients per trainer
            $avgClients = $activeTrainers > 0 ? round($membersWithTrainer / $activeTrainers, 1) : 0;

            // Trainers with clients
            $trainersWithClients = Trainer::query()->where('gym_id', $gymId)
                ->whereHas('members')
                ->count();

            // Utilization rate
            $utilizationRate = $totalTrainers > 0 ? round(($trainersWithClients / $totalTrainers) * 100, 1) : 0;

            // Member coverage (% of members with trainer)
            $memberCoverage = $totalActiveMembers > 0
                ? round(($membersWithTrainer / $totalActiveMembers) * 100, 1)
                : 0;

            // Top trainer by client count
            $topTrainer = Trainer::query()->where('gym_id', $gymId)
                ->withCount(['members' => fn (Builder $q) => $q->membershipActive()])
                ->orderByDesc('members_count')
                ->first();

            return [
                'total_trainers' => $totalTrainers,
                'active_trainers' => $activeTrainers,
                'members_with_trainer' => $membersWithTrainer,
                'avg_clients' => $avgClients,
                'trainers_with_clients' => $trainersWithClients,
                'utilization_rate' => $utilizationRate,
                'member_coverage' => $memberCoverage,
                'top_trainer_name' => $topTrainer?->name ?? 'N/A',
                'top_trainer_clients' => $topTrainer?->members_count ?? 0,
            ];
        });
    }

    /**
     * Get trainer client distribution.
     */
    public function getTrainerDistribution(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = ReportCacheService::TRAINER_DISTRIBUTION . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            return Trainer::query()->where('gym_id', $gymId)
                ->withCount(['members' => fn ($q) => $q->membershipActive()])
                ->orderByDesc('members_count')
                ->get()
                ->map(fn ($trainer) => [
                    'name' => $trainer->name,
                    'clients' => $trainer->members_count,
                    'specialization' => $trainer->specialization ?? 'General',
                ])
                ->all();
        });
    }

    protected function getHeaderActions(): array
    {
        return array_merge([
            Action::make('export')
                ->label('Export to Excel')
                ->icon('phosphor-download')
                ->color('success')
                ->action(function () {
                    /** @var Gym $gym */
                    $gym = Filament::getTenant();

                    return new TrainerReportExport($gym?->id, $this->filters);
                }),
            Action::make('refresh')
                ->label('Refresh')
                ->icon('phosphor-arrows-clockwise')
                ->color('gray')
                ->action(fn () => $this->dispatch('$refresh')),
        ], $this->getFilterActions(), $this->getExportActions());
    }

    protected function getFooterWidgets(): array
    {
        return [
            TrainerClientChart::class,
            TrainerUtilizationWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }

    /**
     * Get shared report data.
     */
    public function getSharedReportData(): array
    {
        return [
            'stats' => $this->getSummaryStats(),
            'chart_data' => [
                'client_distribution' => $this->getClientDistributionData(),
            ],
            'performance_metrics' => $this->getPerformanceMetricsData(),
            'monthly_trends' => $this->getMonthlyTrendsData(),
        ];
    }

    /**
     * Get shared report title.
     */
    public function getSharedReportTitle(): string
    {
        return 'Trainer Report';
    }

    /**
     * Get client distribution data for PDF charts.
     */
    public function getClientDistributionData(): array
    {
        $gymId = $this->getGymId();

        return Trainer::query()->where('gym_id', $gymId)
            ->withCount(['members' => fn ($q) => $q->membershipActive()])
            ->orderByDesc('members_count')
            ->limit(10)
            ->get()
            ->map(fn ($trainer) => [
                'name' => $trainer->name,
                'clients' => $trainer->members_count,
            ])
            ->all();
    }

    /**
     * Get performance metrics data for PDF charts.
     */
    public function getPerformanceMetricsData(): array
    {
        $gymId = $this->getGymId();

        $trainers = Trainer::query()->where('gym_id', $gymId)
            ->withCount(['members' => fn ($q) => $q->membershipActive()])
            ->get();

        $totalTrainers = $trainers->count();
        $activeTrainers = $trainers->where('is_available', true)->count();
        $trainersWithClients = $trainers->filter(fn ($t) => $t->members_count > 0)->count();

        return [
            [
                'metric' => 'Total Trainers',
                'value' => $totalTrainers,
            ],
            [
                'metric' => 'Active Trainers',
                'value' => $activeTrainers,
            ],
            [
                'metric' => 'Trainers with Clients',
                'value' => $trainersWithClients,
            ],
            [
                'metric' => 'Utilization Rate',
                'value' => $totalTrainers > 0 ? round(($trainersWithClients / $totalTrainers) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Get monthly trends data for PDF.
     */
    public function getMonthlyTrendsData(): array
    {
        $gymId = $this->getGymId();

        // Get trainer count over time (last 12 months)
        $monthlyData = [];
        $previousValue = null;

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Trainer::query()->where('gym_id', $gymId)
                ->whereDate('created_at', '<=', $date->endOfMonth())
                ->count();

            $changePercent = 0;
            $trend = 'Stable';

            if ($previousValue !== null && $previousValue > 0) {
                $changePercent = round((($count - $previousValue) / $previousValue) * 100, 1);

                if ($changePercent > 5) {
                    $trend = 'Up';
                } elseif ($changePercent < -5) {
                    $trend = 'Down';
                }
            }

            $monthlyData[] = [
                'month' => $date->format('M Y'),
                'value' => $count,
                'change_percent' => $changePercent,
                'trend' => $trend,
            ];

            $previousValue = $count;
        }

        return $monthlyData;
    }
}
