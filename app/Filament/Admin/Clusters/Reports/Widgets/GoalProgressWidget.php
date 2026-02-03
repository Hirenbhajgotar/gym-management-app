<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Enums\PaymentStatus;
use App\Models\Attendances;
use App\Models\GymMember;
use App\Models\Lead;
use App\Models\MemberPayment;
use App\Models\ReportGoal;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class GoalProgressWidget extends Widget
{
    protected static string $view = 'filament.clusters.reports.widgets.goal-progress-widget';

    protected int|string|array $columnSpan = 'full';

    public function getActiveGoals(): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        if (! $gym) {
            return [];
        }

        $cacheKey = ReportGoal::CACHE_KEY_PREFIX . $gym->id;

        return Cache::flexible($cacheKey, [now()->addMinutes(5), now()->addMinutes(15)], function () use ($gym) {
            $goals = ReportGoal::where('gym_id', $gym->id)
                ->active()
                ->current()
                ->get();

            return $goals->map(function (ReportGoal $goal) use ($gym) {
                $currentValue = $this->getCurrentValue($gym->id, $goal);
                $progress = $goal->calculateProgress($currentValue);
                $status = $goal->getProgressStatus($progress);
                $color = $goal->getProgressColor($progress);

                return [
                    'id' => $goal->id,
                    'metric_type' => $goal->metric_type,
                    'metric_label' => ReportGoal::getMetricTypes()[$goal->metric_type] ?? $goal->metric_type,
                    'period' => $goal->period,
                    'period_label' => ReportGoal::getPeriodTypes()[$goal->period] ?? $goal->period,
                    'target' => $goal->target_value,
                    'current' => $currentValue,
                    'progress' => $progress,
                    'status' => $status,
                    'color' => $color,
                    'period_start' => $goal->period_start->format('M d'),
                    'period_end' => $goal->period_end->format('M d, Y'),
                    'remaining' => max(0, $goal->target_value - $currentValue),
                    'days_left' => (int) ceil(now()->floatDiffInDays($goal->period_end, false)),
                ];
            })->toArray();
        });
    }

    /**
     * Get current value for a specific metric type.
     */
    protected function getCurrentValue(int $gymId, ReportGoal $goal): float|int
    {
        return match ($goal->metric_type) {
            'revenue' => MemberPayment::where('gym_id', $gymId)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereBetween('payment_date', [$goal->period_start, $goal->period_end])
                ->sum('amount'),

            'members' => GymMember::where('gym_id', $gymId)
                ->whereBetween('created_at', [$goal->period_start, $goal->period_end->endOfDay()])
                ->count(),

            'leads' => Lead::where('gym_id', $gymId)
                ->whereBetween('created_at', [$goal->period_start, $goal->period_end->endOfDay()])
                ->count(),

            'conversions' => Lead::where('gym_id', $gymId)
                ->where('status', 'converted')
                ->whereBetween('updated_at', [$goal->period_start, $goal->period_end->endOfDay()])
                ->count(),

            'attendance' => Attendances::where('gym_id', $gymId)
                ->whereBetween('check_in_time', [$goal->period_start, $goal->period_end->endOfDay()])
                ->count(),

            'retention' => $this->calculateRetentionRate($gymId, $goal),

            default => 0,
        };
    }

    /**
     * Calculate retention rate for the goal period.
     */
    protected function calculateRetentionRate(int $gymId, ReportGoal $goal): float
    {
        $activeAtStart = GymMember::where('gym_id', $gymId)
            ->where('membership_start', '<=', $goal->period_start)
            ->where(function ($q) use ($goal) {
                $q->where('membership_end', '>=', $goal->period_start)
                    ->orWhereNull('membership_end');
            })
            ->count();

        if ($activeAtStart === 0) {
            return 0;
        }

        $stillActive = GymMember::where('gym_id', $gymId)
            ->where('membership_start', '<=', $goal->period_start)
            ->where(function ($q) use ($goal) {
                $q->where('membership_end', '>=', $goal->period_end)
                    ->orWhereNull('membership_end');
            })
            ->count();

        return round(($stillActive / $activeAtStart) * 100, 1);
    }
}
