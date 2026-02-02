<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Clusters\Reports\Widgets\LeadConversionChart;
use App\Filament\Admin\Clusters\Reports\Widgets\LeadFunnelChart;
use App\Filament\Admin\Clusters\Reports\Widgets\LeadSourceChart;
use App\Models\Lead;
use App\Traits\HasReportFilters;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class LeadReports extends Page implements HasForms
{
    use HasReportFilters;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-funnel';

    protected static ?string $navigationLabel = 'Lead Reports';

    protected static ?string $title = 'Lead Analytics';

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.lead-reports';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->subMonths(6)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'source' => null,
            'status' => null,
        ];
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
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return Lead::query()
            ->where('gym_id', $gym?->id)
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
            LeadFunnelChart::make(['filters' => $this->filters]),
            LeadSourceChart::make(['filters' => $this->filters]),
            LeadConversionChart::make(['filters' => $this->filters]),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }
}
