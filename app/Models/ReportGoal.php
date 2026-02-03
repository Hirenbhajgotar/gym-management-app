<?php

namespace App\Models;

use App\Observers\ReportGoalObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([ReportGoalObserver::class])]
class ReportGoal extends Model
{
    use HasFactory;

    public const CACHE_KEY_PREFIX = 'report:goals_progress:gym:';

    protected $fillable = [
        'gym_id',
        'created_by',
        'metric_type',
        'period',
        'target_value',
        'period_start',
        'period_end',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to active goals.
     */
    #[Scope]
    protected function active($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to current period goals.
     */
    #[Scope]
    protected function current($query)
    {
        return $query->where('period_start', '<=', now())
            ->where('period_end', '>=', now());
    }

    /**
     * Scope by metric type.
     */
    #[Scope]
    protected function ofType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Scope by period type.
     */
    #[Scope]
    protected function ofPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Calculate progress percentage based on current value.
     */
    public function calculateProgress(float|int $currentValue): float
    {
        if ($this->target_value <= 0) {
            return 0;
        }

        return min(round(($currentValue / $this->target_value) * 100, 1), 100);
    }

    /**
     * Get progress status based on percentage.
     */
    public function getProgressStatus(float $progress): string
    {
        return match (true) {
            $progress >= 100 => 'achieved',
            $progress >= 75 => 'on_track',
            $progress >= 50 => 'moderate',
            $progress >= 25 => 'behind',
            default => 'critical',
        };
    }

    /**
     * Get color for progress status.
     */
    public function getProgressColor(float $progress): string
    {
        return match ($this->getProgressStatus($progress)) {
            'achieved' => 'success',
            'on_track' => 'info',
            'moderate' => 'warning',
            'behind' => 'warning',
            'critical' => 'danger',
        };
    }

    /**
     * Get available metric types.
     */
    public static function getMetricTypes(): array
    {
        return [
            'revenue' => 'Revenue',
            'members' => 'New Members',
            'leads' => 'New Leads',
            'conversions' => 'Lead Conversions',
            'attendance' => 'Check-ins',
            'retention' => 'Retention Rate (%)',
        ];
    }

    /**
     * Get available period types.
     */
    public static function getPeriodTypes(): array
    {
        return [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'yearly' => 'Yearly',
        ];
    }
}
