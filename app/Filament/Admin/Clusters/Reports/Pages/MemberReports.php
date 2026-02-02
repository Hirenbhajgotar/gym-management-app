<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Clusters\Reports\Widgets\MemberDemographicsChart;
use App\Filament\Admin\Clusters\Reports\Widgets\MemberGrowthChart;
use App\Filament\Admin\Clusters\Reports\Widgets\MemberPlanDistributionChart;
use App\Filament\Admin\Clusters\Reports\Widgets\MemberStatusChart;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Traits\HasReportFilters;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MemberReports extends Page implements HasForms
{
    use HasReportFilters;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-users-three';

    protected static ?string $navigationLabel = 'Member Reports';

    protected static ?string $title = 'Member Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.member-reports';

    public ?array $filters = [];

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
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return $form
            ->schema([
                Forms\Components\Section::make('Report Filters')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('From Date')
                            ->default(now()->subYear())
                            ->native(false),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('To Date')
                            ->default(now())
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Membership Status')
                            ->options([
                                'active' => 'Active',
                                'expired' => 'Expired',
                            ])
                            ->placeholder('All Status'),

                        Forms\Components\Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->options(
                                MembershipPlan::where('gym_id', $gym?->id)
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
     */
    public function getFilteredMembersQuery()
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return GymMember::query()
            ->where('gym_id', $gym?->id)
            ->when($this->filters['start_date'], fn ($q) => $q->whereDate('created_at', '>=', $this->filters['start_date']))
            ->when($this->filters['end_date'], fn ($q) => $q->whereDate('created_at', '<=', $this->filters['end_date']))
            ->when($this->filters['status'] === 'active', fn ($q) => $q->membershipActive())
            ->when($this->filters['status'] === 'expired', fn ($q) => $q->membershipExpired())
            ->when($this->filters['membership_plan_id'], fn ($q) => $q->where('gym_membership_plan_id', $this->filters['membership_plan_id']));
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
            $membersWithMultiplePayments = GymMember::where('gym_id', $gymId)
                ->whereHas('payments', function ($q) {
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
                'avg_duration_days' => round($avgDuration ?? 0),
                'retention_rate' => $retentionRate,
                'active_rate' => $totalMembers > 0 ? round(($activeMembers / $totalMembers) * 100, 1) : 0,
            ];
        });
    }

    /**
     * Get growth data for chart.
     */
    public function getGrowthData(): Collection
    {
        $cacheKey = $this->getReportCacheKey('member_growth', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(15), now()->addHours(1)], function () {
            $query = $this->getFilteredMembersQuery();

            return $query->clone()
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($item) => [
                    'month' => \Carbon\Carbon::parse($item->month . '-01')->format('M Y'),
                    'count' => $item->count,
                ]);
        });
    }

    /**
     * Get plan distribution data.
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

        return $colors[($planId ?? 0) % count($colors)];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('phosphor-download')
                ->color('success')
                ->action(function () {
                    // TODO: Implement export
                }),

            Action::make('refresh')
                ->label('Refresh')
                ->icon('phosphor-arrows-clockwise')
                ->color('gray')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
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
}
