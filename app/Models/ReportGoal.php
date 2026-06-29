<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Observers\ReportGoalObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([ReportGoalObserver::class])]
/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $created_by
 * @property string $metric_type
 * @property string $period
 * @property numeric $target_value
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property string|null $notes
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read mixed $current_value
 * @property-read mixed $days_left
 * @property-read \App\Models\Gym $gym
 * @property-read mixed $progress
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal current()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal ofPeriod(string $period)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal whereMetricType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal wherePeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal whereTargetValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportGoal whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
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
    // ACCESSORS
    // =========================================================================

    protected function currentValue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->calculateCurrentValue(),
        );
    }

    protected function progress(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->calculateProgress($this->current_value),
        );
    }

    protected function daysLeft(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ceil(now()->diffInDays($this->period_end, false)),
        );
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Calculate current value for a specific metric type.
     */
    public function calculateCurrentValue(): float|int
    {
        return match ($this->metric_type) {
            'revenue' => MemberPayment::query()->where('gym_id', $this->gym_id)
                ->where('status', PaymentStatus::COMPLETED)
                ->whereBetween('payment_date', [$this->period_start, $this->period_end])
                ->sum('amount'),

            'members' => GymMember::query()->where('gym_id', $this->gym_id)
                ->whereBetween('created_at', [$this->period_start, $this->period_end->endOfDay()])
                ->count(),

            'leads' => Lead::query()->where('gym_id', $this->gym_id)
                ->whereBetween('created_at', [$this->period_start, $this->period_end->endOfDay()])
                ->count(),

            'conversions' => Lead::query()->where('gym_id', $this->gym_id)
                ->where('status', 'converted')
                ->whereBetween('updated_at', [$this->period_start, $this->period_end->endOfDay()])
                ->count(),

            'attendance' => Attendances::query()->where('gym_id', $this->gym_id)
                ->whereBetween('check_in_time', [$this->period_start, $this->period_end->endOfDay()])
                ->count(),

            'retention' => $this->calculateRetentionRate(),

            default => 0,
        };
    }

    /**
     * Calculate retention rate for the goal period.
     */
    protected function calculateRetentionRate(): float
    {
        $activeAtStart = GymMember::query()->where('gym_id', $this->gym_id)
            ->where('membership_start', '<=', $this->period_start)
            ->where(function (Builder $q) {
                $q->where('membership_end', '>=', $this->period_start)
                    ->orWhereNull('membership_end');
            })
            ->count();

        if ($activeAtStart === 0) {
            return 0;
        }

        $stillActive = GymMember::query()->where('gym_id', $this->gym_id)
            ->where('membership_start', '<=', $this->period_start)
            ->where(function (Builder $q) {
                $q->where('membership_end', '>=', $this->period_end)
                    ->orWhereNull('membership_end');
            })
            ->count();

        return round(($stillActive / $activeAtStart) * 100, 1);
    }

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
            default => 'gray',
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
