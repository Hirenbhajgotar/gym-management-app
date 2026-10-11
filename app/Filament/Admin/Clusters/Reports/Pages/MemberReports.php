<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\PaymentStatus;
use App\Exports\MemberReportExport;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Clusters\Reports\Widgets\MemberDemographicsChart;
use App\Filament\Admin\Clusters\Reports\Widgets\MemberGrowthChart;
use App\Filament\Admin\Clusters\Reports\Widgets\MemberPlanDistributionChart;
use App\Filament\Admin\Clusters\Reports\Widgets\MemberStatusChart;
use App\Filament\BasePages\BasePage;
use App\Interfaces\ShareableReport;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Services\ChurnRiskService;
use App\Services\ReportCacheService;
use App\Traits\HasReportFilters;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

class MemberReports extends BasePage implements HasForms, ShareableReport
{
    use HasReportFilters;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-users-three';

    protected static ?string $navigationLabel = 'Member Reports';

    protected static ?string $title = 'Member Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.member-reports';

    /**
     * Get Livewire listeners for real-time updates via Echo.
     */
    protected function getListeners(): array
    {
        $gymId = $this->getGymId();

        return [
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => 'handleMemberStatsUpdate',
        ];
    }

    /**
     * Handle real-time member stats update - clears cache and refreshes data.
     */
    public function handleMemberStatsUpdate(): void
    {
        // Clear cached data to ensure fresh data is loaded
        $this->clearReportCache('member_summary');
        $this->clearReportCache('member_growth');
        $this->clearReportCache('member_plan_distribution');

        // Also clear the churn stats cache
        $gymId = $this->getGymId();
        Cache::forget(ReportCacheService::CHURN_STATS . $gymId);

        // Dispatch refresh to widgets
        $this->dispatch('refresh-widgets');
    }

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'membership_plan_id' => null,
        ];
    }

    public function form(Form $form): Form
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        return $form
            ->schema([
                Section::make('Report Filters')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('From Date')
                            ->default(now()->subYear())
                            ->native(false),

                        DatePicker::make('end_date')
                            ->label('To Date')
                            ->default(now())
                            ->native(false),

                        Select::make('status')
                            ->label('Membership Status')
                            ->options([
                                'active' => 'Active',
                                'expired' => 'Expired',
                            ])
                            ->placeholder('All Status'),

                        Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->options(
                                MembershipPlan::query()->where('gym_id', $gym?->id)
                                    ->pluck('name', 'id')
                            )
                            ->placeholder('All Plans'),
                    ])
                    ->columns(4),
            ])
            ->statePath('filters');
    }

    /**
     * Get filtered members query builder.
     *
     * @return Builder<GymMember>
     */
    public function getFilteredMembersQuery(): Builder
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        return GymMember::query()
            ->where('gym_id', $gym?->id)
            ->when($this->filters['start_date'] ?? null, fn ($q) => $q->whereDate('created_at', '>=', $this->filters['start_date']))
            ->when($this->filters['end_date'] ?? null, fn ($q) => $q->whereDate('created_at', '<=', $this->filters['end_date']))
            ->when(($this->filters['status'] ?? null) === 'active', fn ($q) => $q->membershipActive())
            ->when(($this->filters['status'] ?? null) === 'expired', fn ($q) => $q->membershipExpired())
            ->when($this->filters['membership_plan_id'] ?? null, fn ($q) => $q->where('gym_membership_plan_id', $this->filters['membership_plan_id']));
    }

    /**
     * Get summary statistics.
     */
    public function getSummaryStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = $this->getReportCacheKey('member_summary', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(10), now()->addMinutes(30)], function () use ($gymId) {
            $query = $this->getFilteredMembersQuery();

            $totalMembers = $query->clone()->count();
            $activeMembers = $query->clone()->membershipActive()->count();
            $expiredMembers = $query->clone()->membershipExpired()->count();
            $withTrainer = $query->clone()->hasTrainer()->count();

            // Average membership duration
            $avgDuration = $query->clone()
                ->whereNotNull('membership_start')
                ->whereNotNull('membership_end')
                ->selectRaw('AVG(DATEDIFF(membership_end, membership_start)) as avg_days')
                ->value('avg_days');

            // Retention rate (members who renewed at least once)
            $membersWithMultiplePayments = GymMember::query()->where('gym_id', $gymId)
                ->whereHas('payments', function (Builder $q) {
                    $q->where('status', PaymentStatus::COMPLETED);
                }, '>', 1)
                ->count();

            $retentionRate = $totalMembers > 0
                ? round(($membersWithMultiplePayments / $totalMembers) * 100, 1)
                : 0;

            return [
                'total' => $totalMembers,
                'active' => $activeMembers,
                'expired' => $expiredMembers,
                'with_trainer' => $withTrainer,
                'avg_duration_days' => round((float) ($avgDuration ?? 0)),
                'retention_rate' => $retentionRate,
                'active_rate' => $totalMembers > 0 ? round(($activeMembers / $totalMembers) * 100, 1) : 0,
            ];
        });
    }

    /**
     * Get growth data for chart.
     *
     * @return Collection<int, array{month: string, count: int, growth_rate: float, retention_rate: int}>
     */
    public function getGrowthData(): Collection
    {
        $cacheKey = $this->getReportCacheKey('member_growth', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(15), now()->addHours(1)], function () {
            $query = $this->getFilteredMembersQuery();

            $data = $query->clone()
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $previousCount = 0;

            return $data->map(function ($item) use (&$previousCount) {
                $growthRate = 0;
                if ($previousCount > 0) {
                    $growthRate = (($item->count - $previousCount) / $previousCount) * 100;
                }

                $currentCount = $item->count;
                $previousCount = $currentCount;

                return [
                    'month' => Date::parse($item->month . '-01')->format('M Y'),
                    'count' => $item->count,
                    'growth_rate' => round($growthRate, 1),
                    'retention_rate' => 100, // Placeholder as we don't have historical retention strictly per month in this query
                ];
            });
        });
    }

    /**
     * Get plan distribution data.
     *
     * @return Collection<int, array{name: string, count: int, revenue: float|int, color: string}>
     */
    public function getPlanDistribution(): Collection
    {
        $cacheKey = $this->getReportCacheKey('member_plan_distribution', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(15), now()->addHours(1)], function () {
            return $this->getFilteredMembersQuery()
                ->with('membershipPlan')
                ->get()
                ->groupBy('gym_membership_plan_id')
                ->map(function ($members, $planId) {
                    $plan = $members->first()->membershipPlan;

                    return [
                        'name' => $plan?->name ?? 'No Plan',
                        'count' => $members->count(),
                        'revenue' => $members->count() * ($plan?->price ?? 0),
                        'color' => $this->getPlanColor($planId),
                    ];
                })
                ->sortByDesc('count')
                ->values();
        });
    }

    /**
     * Get color for membership plan.
     */
    protected function getPlanColor(int|string|null $planId): string
    {
        $colors = [
            'rgb(59, 130, 246)',   // blue
            'rgb(16, 185, 129)',   // green
            'rgb(249, 115, 22)',   // orange
            'rgb(139, 92, 246)',   // purple
            'rgb(236, 72, 153)',   // pink
            'rgb(20, 184, 166)',   // teal
        ];

        $numericPlanId = (int) ($planId ?? 0);

        return $colors[$numericPlanId % count($colors)];
    }

    /**
     * Get churn risk statistics.
     */
    public function getChurnStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = ReportCacheService::CHURN_STATS . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $service = new ChurnRiskService();
            $stats = $service->getChurnStats($gymId);
            $atRiskMembers = $service->getAtRiskMembers($gymId, 65, 5);

            return array_merge($stats, [
                'top_at_risk' => $atRiskMembers,
            ]);
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
                    return (new MemberReportExport(
                        gymId: Filament::getTenant()?->id,
                        filters: $this->filters
                    ))->download('member-report-' . now()->format('Y-m-d') . '.xlsx');
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
            MemberGrowthChart::make(['filters' => $this->filters]),
            MemberStatusChart::make(['filters' => $this->filters]),
            MemberPlanDistributionChart::make(['filters' => $this->filters]),
            MemberDemographicsChart::make(['filters' => $this->filters]),
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
            'growth' => $this->getGrowthData()->all(),
            'planDistribution' => $this->getPlanDistribution()->all(),
            'churnStats' => $this->getChurnStats(),
            'chart_data' => [
                'member_growth' => $this->getGrowthData()->all(),
                'plan_distribution' => $this->getPlanDistribution()->all(),
            ],
        ];
    }

    /**
     * Get shared report title.
     */
    public function getSharedReportTitle(): string
    {
        return 'Member Report';
    }
}
