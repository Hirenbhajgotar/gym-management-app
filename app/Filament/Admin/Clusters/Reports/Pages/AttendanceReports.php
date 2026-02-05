<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Clusters\Reports\Widgets\AttendanceHeatmapChart;
use App\Filament\Admin\Clusters\Reports\Widgets\AttendanceTrendChart;
use App\Models\Attendances;
use App\Traits\HasReportFilters;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class AttendanceReports extends Page implements HasForms
{
    use HasReportFilters;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-door-open';

    protected static ?string $navigationLabel = 'Attendance Reports';

    protected static ?string $title = 'Attendance Analytics';

    protected static ?int $navigationSort = 5;

    protected static ?string $cluster = Reports::class;

    protected static string $view = 'filament.clusters.reports.pages.attendance-reports';

    public function mount(): void
    {
        if (empty($this->filters)) {
            $this->filters = [
                'start_date' => now()->subMonth()->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
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
                            ->default(now()->subMonth())
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
     * Get summary statistics.
     */
    public function getSummaryStats(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = $this->getReportCacheKey('attendance_summary', $this->filters);

        return Cache::flexible($cacheKey, [now()->addMinutes(5), now()->addMinutes(15)], function () use ($gymId) {
            $startDate = $this->filters['start_date'];
            $endDate = $this->filters['end_date'];

            // Total check-ins in period
            $totalCheckIns = Attendances::where('gym_id', $gymId)
                ->whereBetween('check_in_time', [$startDate, $endDate . ' 23:59:59'])
                ->count();

            // Unique members who checked in
            $uniqueMembers = Attendances::where('gym_id', $gymId)
                ->whereBetween('check_in_time', [$startDate, $endDate . ' 23:59:59'])
                ->distinct('attendanceable_id')
                ->count('attendanceable_id');

            // Calculate days in range
            $daysInRange = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

            // Average daily check-ins
            $avgDaily = $daysInRange > 0 ? round($totalCheckIns / $daysInRange, 1) : 0;

            // Today's stats
            $todayCheckIns = Attendances::where('gym_id', $gymId)
                ->whereDate('check_in_time', today())
                ->count();

            $currentlyIn = Attendances::where('gym_id', $gymId)
                ->whereDate('check_in_time', today())
                ->where('status', 'checked_in')
                ->count();

            // Peak hour (most common check-in hour)
            $peakHour = Attendances::where('gym_id', $gymId)
                ->whereBetween('check_in_time', [$startDate, $endDate . ' 23:59:59'])
                ->selectRaw('HOUR(check_in_time) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderByDesc('count')
                ->first();

            // Peak day of week
            $peakDay = Attendances::where('gym_id', $gymId)
                ->whereBetween('check_in_time', [$startDate, $endDate . ' 23:59:59'])
                ->selectRaw('DAYOFWEEK(check_in_time) as day, COUNT(*) as count')
                ->groupBy('day')
                ->orderByDesc('count')
                ->first();

            // Average session duration (check-in to check-out)
            $avgDuration = Attendances::where('gym_id', $gymId)
                ->whereBetween('check_in_time', [$startDate, $endDate . ' 23:59:59'])
                ->whereNotNull('check_out_time')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)) as avg_minutes')
                ->value('avg_minutes');

            return [
                'total_check_ins' => $totalCheckIns,
                'unique_members' => $uniqueMembers,
                'avg_daily' => $avgDaily,
                'today_check_ins' => $todayCheckIns,
                'currently_in' => $currentlyIn,
                'peak_hour' => $peakHour?->hour !== null ? Carbon::createFromTime($peakHour->hour)->format('g A') : 'N/A',
                'peak_day' => $peakDay?->day ? Carbon::now()->dayOfWeek($peakDay->day - 1)->format('l') : 'N/A',
                'avg_duration_minutes' => round($avgDuration ?? 0),
            ];
        });
    }

    /**
     * Get hourly distribution for today.
     */
    public function getHourlyDistribution(): array
    {
        $gymId = $this->getGymId();
        $cacheKey = "reports:hourly_distribution:gym:{$gymId}:" . today()->format('Y-m-d');

        return Cache::flexible($cacheKey, [now()->addMinutes(5), now()->addMinutes(15)], function () use ($gymId) {
            $distribution = Attendances::where('gym_id', $gymId)
                ->whereDate('check_in_time', today())
                ->selectRaw('HOUR(check_in_time) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->pluck('count', 'hour')
                ->toArray();

            // Get actual operating hours from the data (or use 0-23 for full day)
            $minHour = $distribution ? min(array_keys($distribution)) : 0;
            $maxHour = $distribution ? max(array_keys($distribution)) : 23;

            // Expand range slightly to show context
            $startHour = max(0, $minHour - 1);
            $endHour = min(23, $maxHour + 1);

            $hours = [];
            $counts = [];

            for ($h = $startHour; $h <= $endHour; $h++) {
                $hours[] = Carbon::createFromTime($h)->format('g A');
                $counts[] = $distribution[$h] ?? 0;
            }

            return [
                'hours' => $hours,
                'counts' => $counts,
            ];
        });
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

    protected function getFooterWidgets(): array
    {
        return [
            AttendanceTrendChart::make(['filters' => $this->filters]),
            AttendanceHeatmapChart::make(['filters' => $this->filters]),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }

    /**
     * Get daily patterns data for PDF charts.
     */
    public function getDailyPatternsData(): array
    {
        $startDate = $this->filters['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');
        $gymId = $this->getGymId();

        return Attendances::where('gym_id', $gymId)
            ->whereBetween('check_in_time', [$startDate, $endDate . ' 23:59:59'])
            ->selectRaw('DATE(check_in_time) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M d'),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get weekly trends data for PDF charts.
     */
    public function getWeeklyTrendsData(): array
    {
        $startDate = $this->filters['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');
        $gymId = $this->getGymId();

        $weeklyData = Attendances::where('gym_id', $gymId)
            ->whereBetween('check_in_time', [$startDate, $endDate . ' 23:59:59'])
            ->selectRaw('DAYOFWEEK(check_in_time) as day, COUNT(*) as count')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return $weeklyData->map(function ($item) use ($dayNames) {
            return [
                'day' => $dayNames[$item->day - 1] ?? 'Unknown',
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get monthly trends data for PDF.
     */
    public function getMonthlyTrendsData(): array
    {
        $startDate = $this->filters['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $this->filters['end_date'] ?? now()->format('Y-m-d');
        $gymId = $this->getGymId();

        $monthlyData = Attendances::where('gym_id', $gymId)
            ->whereBetween('check_in_time', [$startDate, $endDate . ' 23:59:59'])
            ->selectRaw("DATE_FORMAT(check_in_time, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $trends = [];
        $previousValue = null;

        foreach ($monthlyData as $item) {
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
                'month' => Carbon::parse($item->month . '-01')->format('M Y'),
                'value' => $currentValue,
                'change_percent' => $changePercent,
                'trend' => $trend,
            ];

            $previousValue = $currentValue;
        }

        return $trends;
    }
}
