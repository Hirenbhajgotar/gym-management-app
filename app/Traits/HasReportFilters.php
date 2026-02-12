<?php

namespace App\Traits;

use App\Models\ShareableReportLink;
use App\Services\ReportCacheService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trait for report pages with common caching and filtering functionality.
 */
trait HasReportFilters
{
    /**
     * Get the current tenant (gym) ID.
     */
    public ?int $manualGymId = null;

    /**
     * Report filters.
     */
    public array $filters = [];

    /**
     * Get the current tenant (gym) ID.
     */
    protected function getGymId(): ?int
    {
        return $this->manualGymId ?? Filament::getTenant()?->id;
    }

    /**
     * Generate a cache key for report data.
     */
    protected function getReportCacheKey(string $reportType, array $filters = []): string
    {
        $gymId = $this->getGymId();
        $filterHash = md5(json_encode($filters));

        return "reports:{$reportType}:gym:{$gymId}:{$filterHash}";
    }

    /**
     * Get cached report data or compute and cache it.
     *
     * @param  string  $reportType  Unique identifier for the report
     * @param  callable  $callback  Function that computes the report data
     * @param  int  $ttlMinutes  Cache TTL in minutes (default: 15)
     */
    protected function getCachedReportData(string $reportType, callable $callback, int $ttlMinutes = 15): mixed
    {
        $cacheKey = $this->getReportCacheKey($reportType, $this->filters ?? []);

        return Cache::flexible(
            $cacheKey,
            [now()->addMinutes($ttlMinutes), now()->addMinutes($ttlMinutes * 2)],
            $callback
        );
    }

    /**
     * Clear the cache for a specific report type.
     */
    protected function clearReportCache(string $reportType): void
    {
        $cacheKey = $this->getReportCacheKey($reportType, $this->filters ?? []);
        Cache::forget($cacheKey);
    }

    /**
     * Format currency for display.
     */
    protected function formatCurrency(float|int $amount, string $currency = 'INR'): string
    {
        return \Illuminate\Support\Number::currency($amount, in: $currency);
    }

    /**
     * Format percentage for display.
     */
    protected function formatPercentage(float|int $value, int $decimals = 1): string
    {
        return number_format($value, $decimals) . '%';
    }

    /**
     * Calculate percentage change between two values.
     */
    protected function calculatePercentageChange(float|int $current, float|int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get color based on trend (positive/negative).
     */
    protected function getTrendColor(float|int $change): string
    {
        if ($change > 0) {
            return 'success';
        } elseif ($change < 0) {
            return 'danger';
        }

        return 'gray';
    }

    /**
     * Get trend icon based on change direction.
     */
    protected function getTrendIcon(float|int $change): string
    {
        if ($change > 0) {
            return 'heroicon-m-arrow-trending-up';
        } elseif ($change < 0) {
            return 'heroicon-m-arrow-trending-down';
        }

        return 'heroicon-m-minus';
    }

    // =========================================================================
    // COMPARATIVE ANALYTICS METHODS (Phase 1)
    // =========================================================================

    /**
     * Get comparison data for current period vs previous period.
     *
     * @param  string  $startDate  Start of current period
     * @param  string  $endDate  End of current period
     * @return array{previous_start: string, previous_end: string, days_in_period: int}
     */
    protected function getComparisonPeriod(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $daysInPeriod = $start->diffInDays($end) + 1;

        // Calculate equivalent previous period
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($daysInPeriod - 1);

        return [
            'previous_start' => $previousStart->format('Y-m-d'),
            'previous_end' => $previousEnd->format('Y-m-d'),
            'days_in_period' => $daysInPeriod,
        ];
    }

    /**
     * Get Year-over-Year comparison dates.
     */
    protected function getYoYPeriod(string $startDate, string $endDate): array
    {
        return [
            'previous_start' => Carbon::parse($startDate)->subYear()->format('Y-m-d'),
            'previous_end' => Carbon::parse($endDate)->subYear()->format('Y-m-d'),
        ];
    }

    /**
     * Get Month-over-Month comparison dates.
     */
    protected function getMoMPeriod(string $startDate, string $endDate): array
    {
        return [
            'previous_start' => Carbon::parse($startDate)->subMonth()->format('Y-m-d'),
            'previous_end' => Carbon::parse($endDate)->subMonth()->format('Y-m-d'),
        ];
    }

    /**
     * Calculate comparison metrics between current and previous values.
     *
     * @param  float|int  $currentValue  Current period value
     * @param  float|int  $previousValue  Previous period value
     * @param  bool  $higherIsBetter  Whether higher values are positive (default: true)
     */
    protected function calculateComparisonMetrics(
        float|int $currentValue,
        float|int $previousValue,
        bool $higherIsBetter = true
    ): array {
        $change = $currentValue - $previousValue;
        $percentChange = $this->calculatePercentageChange($currentValue, $previousValue);

        // Determine if the change is positive based on context
        $isPositive = $higherIsBetter ? $change >= 0 : $change <= 0;

        return [
            'current' => $currentValue,
            'previous' => $previousValue,
            'change' => $change,
            'percent_change' => $percentChange,
            'is_positive' => $isPositive,
            'color' => $isPositive ? 'success' : 'danger',
            'icon' => $this->getTrendIcon($change),
            'formatted_change' => ($change >= 0 ? '+' : '') . number_format($change, 0),
            'formatted_percent' => ($percentChange >= 0 ? '+' : '') . $percentChange . '%',
        ];
    }

    /**
     * Get cohort data for member retention analysis.
     *
     * @param  int  $months  Number of months to analyze
     * @return Collection<int, array{cohort: string, initial: int, retained: array<int, int>}>
     */
    protected function getMemberCohortData(int $months = 6): Collection
    {
        $gymId = $this->getGymId();

        return Cache::flexible(
            ReportCacheService::COHORT . $gymId . ':months:' . $months,
            ReportCacheService::getCacheTtl(),
            function () use ($gymId, $months) {
                $cohorts = collect();

                for ($i = $months - 1; $i >= 0; $i--) {
                    $cohortMonth = now()->subMonths($i)->startOfMonth();
                    $cohortLabel = $cohortMonth->format('M Y');

                    // Get members who joined in this cohort month
                    $cohortMembers = DB::table('gym_members')
                        ->where('gym_id', $gymId)
                        ->whereYear('created_at', $cohortMonth->year)
                        ->whereMonth('created_at', $cohortMonth->month)
                        ->pluck('id');

                    $initialCount = $cohortMembers->count();

                    if ($initialCount === 0) {
                        continue;
                    }

                    // Track retention for each subsequent month
                    $retained = [];
                    for ($m = 0; $m <= $i; $m++) {
                        $checkMonth = $cohortMonth->copy()->addMonths($m);

                        // Count how many from this cohort were still active
                        $retainedCount = DB::table('gym_members')
                            ->whereIn('id', $cohortMembers)
                            ->where(function ($query) use ($checkMonth) {
                                $query->where('membership_end', '>=', $checkMonth->endOfMonth())
                                    ->orWhereNull('membership_end');
                            })
                            ->count();

                        $retained[$m] = $initialCount > 0
                            ? round(($retainedCount / $initialCount) * 100, 1)
                            : 0;
                    }

                    $cohorts->push([
                        'cohort' => $cohortLabel,
                        'initial' => $initialCount,
                        'retained' => $retained,
                    ]);
                }

                return $cohorts;
            }
        );
    }

    /**
     * Get sparkline data for the last N days.
     *
     * @param  string  $table  Table name
     * @param  string  $dateColumn  Date column name
     * @param  string  $aggregation  Aggregation type: 'count' or 'sum'
     * @param  string|null  $sumColumn  Column to sum (required if aggregation is 'sum')
     * @param  int  $days  Number of days
     * @param  array  $conditions  Additional where conditions
     */
    protected function getSparklineData(
        string $table,
        string $dateColumn,
        string $aggregation = 'count',
        ?string $sumColumn = null,
        int $days = 30,
        array $conditions = []
    ): array {
        $gymId = $this->getGymId();
        $startDate = now()->subDays($days - 1)->startOfDay();

        $query = DB::table($table)
            ->where('gym_id', $gymId)
            ->where($dateColumn, '>=', $startDate);

        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        if ($aggregation === 'sum' && $sumColumn) {
            $data = $query
                ->selectRaw("DATE($dateColumn) as date, SUM($sumColumn) as value")
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('value', 'date')
                ->toArray();
        } else {
            $data = $query
                ->selectRaw("DATE($dateColumn) as date, COUNT(*) as value")
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('value', 'date')
                ->toArray();
        }

        // Fill in missing dates with 0
        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($days - 1 - $i)->format('Y-m-d');
            $result[] = $data[$date] ?? 0;
        }

        return $result;
    }

    /**
     * Get common actions for saving/loading filters.
     */
    public function getFilterActions(): array
    {
        return [
            \Filament\Actions\ActionGroup::make([
                \Filament\Actions\Action::make('save_filter')
                    ->label('Save Current View')
                    ->icon('phosphor-floppy-disk')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('Filter Name'),
                        \Filament\Forms\Components\Checkbox::make('is_default')
                            ->label('Set as Default'),
                    ])
                    ->action(function (array $data) {
                        \App\Models\SavedReportFilter::create([
                            'gym_id' => $this->getGymId(),
                            'user_id' => \Illuminate\Support\Facades\Auth::id(),
                            'report_type' => static::class,
                            'name' => $data['name'],
                            'filters' => $this->filters,
                            'is_default' => $data['is_default'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Filter Saved')
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('load_filter')
                    ->label('Load Saved View')
                    ->icon('phosphor-folder-open')
                    ->form([
                        \Filament\Forms\Components\Select::make('filter_id')
                            ->label('Select Saved View')
                            ->options(function () {
                                return \App\Models\SavedReportFilter::where('gym_id', $this->getGymId())
                                    ->where('report_type', static::class)
                                    ->pluck('name', 'id');
                            })
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $savedFilter = \App\Models\SavedReportFilter::find($data['filter_id']);
                        if ($savedFilter) {
                            $this->filters = $savedFilter->filters;
                            $this->dispatch('$refresh');

                            \Filament\Notifications\Notification::make()
                                ->title('Filter Loaded')
                                ->success()
                                ->send();
                        }
                    }),
            ])
                ->label('Saved Views')
                ->icon('phosphor-bookmark')
                ->color('gray'),
        ];
    }

    /**
     * Get actions for exporting reports.
     */
    public function getExportActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('phosphor-file-pdf')
                ->color('danger')
                ->action(function () {
                    $report = new \App\Models\ScheduledReport;
                    $report->report_type = static::class;
                    $report->gym_id = $this->getGymId();
                    $report->name = static::$title ?? static::$navigationLabel ?? 'Report';
                    $report->filters = $this->filters ?? [];
                    $report->setRelation('gym', \App\Models\Gym::find($this->getGymId()));

                    $pdfContent = app(\App\Services\ReportPdfGenerator::class)->generate($report);

                    return response()->streamDownload(
                        fn () => print ($pdfContent),
                        \Illuminate\Support\Str::slug(static::$title ?? static::$navigationLabel ?? 'Report') . '-' . now()->format('Y-m-d') . '.pdf'
                    );
                }),

            \Filament\Actions\Action::make('share_report')
                ->label('Share Report')
                ->icon('phosphor-share-network')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Share Name (Optional)')
                        ->placeholder('e.g., Monthly Report for Investors'),
                    \Filament\Forms\Components\Select::make('expires_in')
                        ->label('Link Expires In')
                        ->options([
                            '7' => '7 Days',
                            '30' => '30 Days',
                            '90' => '90 Days',
                            '365' => '1 Year',
                            '0' => 'Never',
                        ])
                        ->default('30')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $shareableLink = ShareableReportLink::create([
                        'gym_id' => $this->getGymId(),
                        'user_id' => Auth::id(),
                        'report_type' => static::class,
                        'filters' => $this->filters ?? [],
                        'expires_at' => $data['expires_in'] > 0
                            ? now()->addDays((int) $data['expires_in'])
                            : null,
                    ]);

                    $shareUrl = $shareableLink->getShareableUrl();

                    // Copy to clipboard notification
                    \Filament\Notifications\Notification::make()
                        ->title('Report Shared Successfully')
                        ->body('Shareable link created and copied to clipboard!')
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view_link')
                                ->label('View Link')
                                ->url($shareUrl)
                                ->openUrlInNewTab(),
                        ])
                        ->send();

                    // Copy to clipboard using JavaScript
                    $this->dispatch('copy-to-clipboard', text: $shareUrl);
                }),
        ];
    }
}
