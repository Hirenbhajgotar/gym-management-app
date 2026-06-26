<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Exports\LeadReportExport;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Clusters\Reports\Widgets\LeadConversionChart;
use App\Filament\Admin\Clusters\Reports\Widgets\LeadFunnelChart;
use App\Filament\Admin\Clusters\Reports\Widgets\LeadSourceChart;
use App\Filament\BasePages\BasePage;
use App\Interfaces\ShareableReport;
use App\Models\Lead;
use App\Services\ReportCacheService;
use App\Traits\HasReportFilters;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Cache;

class LeadReports extends BasePage implements HasForms, ShareableReport
{
    use HasReportFilters;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-funnel';

    protected static ?string $navigationLabel = 'Lead Reports';

    protected static ?string $title = 'Lead Analytics';

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.lead-reports';

    /**
     * Get Livewire listeners for real-time updates via Echo.
     */
    protected function getListeners(): array
    {
        $gymId = $this->getGymId();

        return [
            "echo-private:reports.{$gymId},.LeadStatsUpdated" => '$refresh',
        ];
    }

    public function mount(): void
    {
        if (empty($this->filters)) {
            $this->filters = [
                'start_date' => now()->subMonths(6)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'source' => null,
                'status' => null,
            ];
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Filters')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('From Date')
                            ->default(now()->subMonths(6))
                            ->native(false),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('To Date')
                            ->default(now())
                            ->native(false),

                        Forms\Components\Select::make('source')
                            ->label('Lead Source')
                            ->options(LeadSource::class)
                            ->placeholder('All Sources'),

                        Forms\Components\Select::make('status')
                            ->label('Lead Status')
                            ->options(LeadStatus::class)
                            ->placeholder('All Status'),
                    ])
                    ->columns(4),
            ])
            ->statePath('filters');
    }

    /**
     * Get filtered leads query builder.
     */
    public function getFilteredLeadsQuery()
    {
        return Lead::query()
            ->where('gym_id', $this->getGymId())
            ->when($this->filters['start_date'], fn ($q) => $q->whereDate('created_at', '>=', $this->filters['start_date']))
            ->when($this->filters['end_date'], fn ($q) => $q->whereDate('created_at', '<=', $this->filters['end_date']))
            ->when($this->filters['source'], fn ($q) => $q->where('source', $this->filters['source']))
            ->when($this->filters['status'], fn ($q) => $q->where('status', $this->filters['status']));
    }

    /**
     * Get summary statistics.
     */
    public function getSummaryStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = $this->getReportCacheKey('lead_summary', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(10), now()->addMinutes(30)], function () use ($gymId) {
            // Total leads in period
            $totalLeads = $this->getFilteredLeadsQuery()->clone()->count();

            // Status breakdown
            $newLeads = $this->getFilteredLeadsQuery()->clone()->where('status', LeadStatus::NEW)->count();
            $contactedLeads = $this->getFilteredLeadsQuery()->clone()->where('status', LeadStatus::CONTACTED)->count();
            $convertedLeads = $this->getFilteredLeadsQuery()->clone()->where('status', LeadStatus::CONVERTED)->count();
            $lostLeads = $this->getFilteredLeadsQuery()->clone()->where('status', LeadStatus::LOST)->count();

            // Conversion rate
            $closedLeads = $convertedLeads + $lostLeads;
            $conversionRate = $closedLeads > 0 ? round(($convertedLeads / $closedLeads) * 100, 1) : 0;

            // Overall conversion rate (converted / total)
            $overallConversion = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

            // Active pipeline
            $activePipeline = $totalLeads - $convertedLeads - $lostLeads;

            // This month stats
            $thisMonthLeads = Lead::where('gym_id', $gymId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $thisMonthConverted = Lead::where('gym_id', $gymId)
                ->where('status', LeadStatus::CONVERTED)
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count();

            return [
                'total' => $totalLeads,
                'new' => $newLeads,
                'contacted' => $contactedLeads,
                'converted' => $convertedLeads,
                'lost' => $lostLeads,
                'conversion_rate' => $conversionRate,
                'overall_conversion' => $overallConversion,
                'active_pipeline' => $activePipeline,
                'this_month_leads' => $thisMonthLeads,
                'this_month_converted' => $thisMonthConverted,
            ];
        });
    }

    /**
     * Get lead scoring statistics.
     */
    public function getLeadScoringStats(): array
    {
        $gymId = $this->getGymId();
        // Cache for 30 minutes
        $cacheKey = ReportCacheService::LEAD_SCORING_STATS . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $service = new \App\Services\LeadScoringService;
            $stats = $service->getLeadStats($gymId);
            $hotLeads = $service->getScoredLeads($gymId, 5)->where('score', '>=', 60);

            return array_merge($stats, [
                'top_leads' => $hotLeads,
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
                    $gymId = $this->getGymId();
                    $filename = 'lead-report-' . now()->format('Y-m-d') . '.xlsx';

                    return (new LeadReportExport($gymId, $this->filters))->download($filename);
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
            LeadFunnelChart::make(['filters' => $this->filters]),
            LeadSourceChart::make(['filters' => $this->filters]),
            LeadConversionChart::make(['filters' => $this->filters]),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }

    /**
     * Get lead funnel data for PDF charts.
     */
    public function getLeadFunnelData(): array
    {
        $startDate = $this->filters['start_date'] ?? now()->subMonths(6)->format('Y-m-d');
        $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');
        $gymId = $this->getGymId();

        $statusCounts = Lead::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $data = [];
        foreach (LeadStatus::cases() as $status) {
            $count = $statusCounts->get($status->value, 0);

            if ($count > 0) {
                $data[] = [
                    'status' => $status->getLabel(),
                    'count' => $count,
                ];
            }
        }

        return $data;
    }

    /**
     * Get conversion rates data for PDF charts.
     */
    public function getConversionRatesData(): array
    {
        $startDate = $this->filters['start_date'] ?? now()->subMonths(6)->format('Y-m-d');
        $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');
        $gymId = $this->getGymId();

        $newLeads = Lead::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        $conversions = Lead::where('gym_id', $gymId)
            ->where('status', LeadStatus::CONVERTED)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        $allMonths = $newLeads->keys()->merge($conversions->keys())->unique()->sort();

        $data = [];
        foreach ($allMonths as $month) {
            $data[] = [
                'month' => \Carbon\Carbon::parse($month . '-01')->format('M Y'),
                'new_leads' => $newLeads->get($month, 0),
                'converted' => $conversions->get($month, 0),
            ];
        }

        return $data;
    }

    /**
     * Get lead sources data for PDF charts.
     */
    public function getLeadSourcesData(): array
    {
        $startDate = $this->filters['start_date'] ?? now()->subMonths(6)->format('Y-m-d');
        $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');
        $gymId = $this->getGymId();

        return Lead::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                $source = $item->source;

                return [
                    'name' => $source?->getLabel() ?? ucfirst(str_replace('_', ' ', $item->source ?? 'Unknown')),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get monthly trends data for PDF.
     */
    public function getMonthlyTrendsData(): array
    {
        $startDate = $this->filters['start_date'] ?? now()->subMonths(6)->format('Y-m-d');
        $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');
        $gymId = $this->getGymId();

        $monthlyLeads = Lead::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $trends = [];
        $previousValue = null;

        foreach ($monthlyLeads as $item) {
            $currentValue = $item->total;
            $changePercent = 0;
            $trend = 'Stable';

            if ($previousValue !== null && $previousValue > 0) {
                $changePercent = round((($currentValue - $previousValue) / $previousValue) * 100, 1);

                if ($changePercent > 5) {
                    $trend = 'Up';
                } elseif ($changePercent < -5) {
                    $trend = 'Down';
                }
            }

            $trends[] = [
                'month' => \Carbon\Carbon::parse($item->month . '-01')->format('M Y'),
                'value' => $currentValue,
                'change_percent' => $changePercent,
                'trend' => $trend,
            ];

            $previousValue = $currentValue;
        }

        return $trends;
    }

    /**
     * Get shared report data.
     */
    public function getSharedReportData(): array
    {
        return [
            'stats' => $this->getSummaryStats(),
            'chart_data' => [
                'lead_funnel' => $this->getLeadFunnelData(),
                'lead_sources' => $this->getLeadSourcesData(),
                'conversion_rates' => $this->getConversionRatesData(),
            ],
            'monthly_trends' => $this->getMonthlyTrendsData(),
        ];
    }

    /**
     * Get shared report title.
     */
    public function getSharedReportTitle(): string
    {
        return 'Lead Report';
    }
}
