<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Traits\HasReportFilters;
use Filament\Widgets\Widget;

class CohortRetentionChart extends Widget
{
    use HasReportFilters;

    protected static string $view = 'filament.clusters.reports.widgets.cohort-retention-chart';

    protected int|string|array $columnSpan = 'full';

    public int $months = 6;

    public function getCohortData(): array
    {
        return $this->getMemberCohortData($this->months)->toArray();
    }

    /**
     * Get the maximum number of retention columns needed.
     */
    public function getMaxRetentionMonths(): int
    {
        $cohorts = $this->getCohortData();

        if (empty($cohorts)) {
            return 0;
        }

        return max(array_map(fn ($c) => count($c['retained'] ?? []), $cohorts));
    }
}
