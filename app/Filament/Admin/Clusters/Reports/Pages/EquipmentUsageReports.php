<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Filament\Admin\Clusters\Reports;
use App\Models\Attendances;
use App\Traits\HasReportFilters;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class EquipmentUsageReports extends Page implements HasForms
{
    use HasReportFilters;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-barbell';

    protected static ?string $navigationLabel = 'Equipment & Classes';

    protected static ?string $title = 'Usage Analytics';

    protected static ?int $navigationSort = 5;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.equipment-usage-reports';

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
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
                            ->default(now()->startOfMonth())
                            ->native(false),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('To Date')
                            ->default(now())
                            ->native(false),
                    ])
                    ->columns(2),
            ])
            ->statePath('filters');
    }

    /**
     * Get usage statistics.
     */
    public function getUsageStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = $this->getReportCacheKey('usage_stats', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(15), now()->addHours(1)], function () use ($gymId) {
            // Note: In a real scenario, we'd need an 'equipment_id' or 'class_id' on attendance
            // For now, we will simulate this based on check-in patterns or assume future schema updates
            // Just placeholder logic for now using existing 'Attendances' model

            $totalCheckIns = Attendances::where('gym_id', $gymId)
                ->whereDate('check_in_time', '>=', $this->filters['start_date'])
                ->whereDate('check_in_time', '<=', $this->filters['end_date'])
                ->count();

            // Peak Hours Analysis
            $peakHours = Attendances::where('gym_id', $gymId)
                ->whereDate('check_in_time', '>=', $this->filters['start_date'])
                ->whereDate('check_in_time', '<=', $this->filters['end_date'])
                ->selectRaw('HOUR(check_in_time) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderByDesc('count')
                ->limit(5)
                ->get();

            return [
                'total_checkins' => $totalCheckIns,
                'peak_hours' => $peakHours,
                'avg_daily_traffic' => $this->calculateAvgDailyTraffic($totalCheckIns),
            ];
        });
    }

    protected function calculateAvgDailyTraffic(int $total): int
    {
        $days = \Carbon\Carbon::parse($this->filters['start_date'])
            ->diffInDays(\Carbon\Carbon::parse($this->filters['end_date'])) + 1;

        return $days > 0 ? round($total / $days) : 0;
    }

    protected function getHeaderActions(): array
    {
        return array_merge([
            Action::make('refresh')
                ->label('Refresh')
                ->icon('phosphor-arrows-clockwise')
                ->color('gray')
                ->action(fn () => $this->dispatch('$refresh')),
        ], $this->getFilterActions(), $this->getExportActions());
    }
}
