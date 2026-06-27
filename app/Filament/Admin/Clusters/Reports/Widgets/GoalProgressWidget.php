<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Models\Gym;
use App\Enums\DefaultRole;
use App\Filament\BaseWidgets\BaseWidget;
use App\Models\ReportGoal;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class GoalProgressWidget extends BaseWidget
{
    protected static string $view = 'filament.clusters.reports.widgets.goal-progress-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['widget'],
            DefaultRole::RECEPTIONIST->value => [],
            DefaultRole::TRAINER->value => [],
        ];
    }

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()->id;

        return [
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.RevenueStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.LeadStatsUpdated" => '$refresh',
            "echo-private:reports.{$gymId},.AttendanceStatsUpdated" => '$refresh',
        ];
    }

    public function getActiveGoals(): array
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        if (! $gym) {
            return [];
        }

        $cacheKey = ReportGoal::CACHE_KEY_PREFIX . $gym->id;

        return Cache::flexible($cacheKey, [now()->addMinutes(5), now()->addMinutes(15)], function () use ($gym) {
            $goals = ReportGoal::query()->where('gym_id', $gym->id)
                ->active()
                ->current()
                ->get();

            return $goals->map(function (ReportGoal $goal) {
                return [
                    'id' => $goal->id,
                    'metric_type' => $goal->metric_type,
                    'metric_label' => ReportGoal::getMetricTypes()[$goal->metric_type] ?? $goal->metric_type,
                    'period' => $goal->period,
                    'period_label' => ReportGoal::getPeriodTypes()[$goal->period] ?? $goal->period,
                    'target' => $goal->target_value,
                    'current' => $goal->current_value,
                    'progress' => $goal->progress,
                    'status' => $goal->getProgressStatus($goal->progress),
                    'color' => $goal->getProgressColor($goal->progress),
                    'period_start' => $goal->period_start->format('M d'),
                    'period_end' => $goal->period_end->format('M d, Y'),
                    'remaining' => max(0, $goal->target_value - $goal->current_value),
                    'days_left' => $goal->days_left,
                ];
            })->toArray();
        });
    }
}
