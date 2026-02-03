<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\PaymentStatus;
use App\Exports\RevenueReportExport;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Clusters\Reports\Widgets\PaymentMethodChart;
use App\Filament\Admin\Clusters\Reports\Widgets\RevenueByPlanChart;
use App\Filament\Admin\Clusters\Reports\Widgets\RevenueForecastChart;
use App\Filament\Admin\Clusters\Reports\Widgets\RevenueTrendChart;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Traits\HasReportFilters;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class RevenueReports extends Page implements HasForms
{
    use HasReportFilters;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-currency-inr';

    protected static ?string $navigationLabel = 'Revenue Reports';

    protected static ?string $title = 'Revenue Analytics';

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.revenue-reports';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'membership_plan_id' => null,
            'payment_method' => null,
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
                            ->default(now()->startOfYear())
                            ->native(false),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('To Date')
                            ->default(now())
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Payment Status')
                            ->options(PaymentStatus::class)
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
     * Get filtered payments query builder.
     */
    public function getFilteredPaymentsQuery()
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return MemberPayment::query()
            ->where('gym_id', $gym?->id)
            ->when($this->filters['start_date'], fn ($q) => $q->whereDate('payment_date', '>=', $this->filters['start_date']))
            ->when($this->filters['end_date'], fn ($q) => $q->whereDate('payment_date', '<=', $this->filters['end_date']))
            ->when($this->filters['status'], fn ($q) => $q->where('status', $this->filters['status']))
            ->when($this->filters['membership_plan_id'], fn ($q) => $q->where('membership_plan_id', $this->filters['membership_plan_id']));
    }

    /**
     * Get summary statistics.
     */
    public function getSummaryStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = $this->getReportCacheKey('revenue_summary', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(10), now()->addMinutes(30)], function () use ($gymId) {
            // Total revenue (completed payments)
            $totalRevenue = $this->getFilteredPaymentsQuery()
                ->clone()
                ->where('status', PaymentStatus::COMPLETED)
                ->sum('amount');

            // Transaction count
            $transactionCount = $this->getFilteredPaymentsQuery()
                ->clone()
                ->where('status', PaymentStatus::COMPLETED)
                ->count();

            // Average transaction value
            $avgTransaction = $transactionCount > 0 ? round($totalRevenue / $transactionCount, 2) : 0;

            // Pending payments
            $pendingPayments = $this->getFilteredPaymentsQuery()
                ->clone()
                ->where('status', PaymentStatus::PENDING)
                ->sum('amount');

            // Active members count
            $activeMembers = GymMember::where('gym_id', $gymId)->membershipActive()->count();

            // ARPM (Average Revenue Per Member)
            $arpm = $activeMembers > 0 ? round($totalRevenue / $activeMembers, 2) : 0;

            // This month vs last month comparison
            $thisMonthRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount');

            $lastMonthRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereMonth('payment_date', now()->subMonth()->month)
                ->whereYear('payment_date', now()->subMonth()->year)
                ->sum('amount');

            $monthlyChange = $this->calculatePercentageChange($thisMonthRevenue, $lastMonthRevenue);

            // Today's collection
            $todayCollection = MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereDate('payment_date', today())
                ->sum('amount');

            return [
                'total_revenue' => $totalRevenue,
                'transaction_count' => $transactionCount,
                'avg_transaction' => $avgTransaction,
                'pending_payments' => $pendingPayments,
                'arpm' => $arpm,
                'this_month' => $thisMonthRevenue,
                'last_month' => $lastMonthRevenue,
                'monthly_change' => $monthlyChange,
                'monthly_change_color' => $this->getTrendColor($monthlyChange),
                'today_collection' => $todayCollection,
            ];
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('phosphor-download')
                ->color('success')
                ->action(function () {
                    /** @var \App\Models\Gym $gym */
                    $gym = Filament::getTenant();

                    return new RevenueReportExport($gym?->id, $this->filters);
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
            RevenueForecastChart::class,
            RevenueTrendChart::make(['filters' => $this->filters]),
            RevenueByPlanChart::make(['filters' => $this->filters]),
            PaymentMethodChart::make(['filters' => $this->filters]),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }
}
