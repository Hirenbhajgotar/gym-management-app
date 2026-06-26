<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;
use App\Observers\ExpenseBudgetObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $expense_category_id
 * @property string $name
 * @property string|null $description
 * @property numeric $amount
 * @property string $currency
 * @property string $period
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $alert_threshold
 * @property bool $alert_sent
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read ExpenseCategory|null $category
 * @property-read mixed $formatted_amount
 * @property-read mixed $formatted_remaining_amount
 * @property-read mixed $formatted_spent_amount
 * @property-read Gym $gym
 *
 * @method static Builder<static>|ExpenseBudget active()
 * @method static Builder<static>|ExpenseBudget current()
 * @method static Builder<static>|ExpenseBudget forCategory(int $categoryId)
 * @method static Builder<static>|ExpenseBudget forGym(int $gymId)
 * @method static Builder<static>|ExpenseBudget newModelQuery()
 * @method static Builder<static>|ExpenseBudget newQuery()
 * @method static Builder<static>|ExpenseBudget query()
 * @method static Builder<static>|ExpenseBudget whereAlertSent($value)
 * @method static Builder<static>|ExpenseBudget whereAlertThreshold($value)
 * @method static Builder<static>|ExpenseBudget whereAmount($value)
 * @method static Builder<static>|ExpenseBudget whereCreatedAt($value)
 * @method static Builder<static>|ExpenseBudget whereCurrency($value)
 * @method static Builder<static>|ExpenseBudget whereDescription($value)
 * @method static Builder<static>|ExpenseBudget whereEndDate($value)
 * @method static Builder<static>|ExpenseBudget whereExpenseCategoryId($value)
 * @method static Builder<static>|ExpenseBudget whereGymId($value)
 * @method static Builder<static>|ExpenseBudget whereId($value)
 * @method static Builder<static>|ExpenseBudget whereIsActive($value)
 * @method static Builder<static>|ExpenseBudget whereName($value)
 * @method static Builder<static>|ExpenseBudget wherePeriod($value)
 * @method static Builder<static>|ExpenseBudget whereStartDate($value)
 * @method static Builder<static>|ExpenseBudget whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([ExpenseBudgetObserver::class])]
class ExpenseBudget extends Model
{
    use HasFactory, LogsActivity;

    public const NAVIGATION_BADGE_CACHE_KEY = 'expense_budget_navigation_badge_';

    protected $fillable = [
        'gym_id',
        'expense_category_id',
        'name',
        'description',
        'amount',
        'currency',
        'period',
        'start_date',
        'end_date',
        'alert_threshold',
        'alert_sent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'alert_threshold' => 'integer',
            'alert_sent' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('expense_budget')
            ->logOnly(['name', 'amount', 'period', 'start_date', 'end_date', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $amount = Number::currency($this->amount, in: $this->currency);

        return match ($eventName) {
            'created' => "Budget '{$this->name}' of {$amount} was created",
            'updated' => "Budget '{$this->name}' was updated",
            'deleted' => "Budget '{$this->name}' was deleted",
            default => "Budget '{$this->name}' was {$eventName}",
        };
    }

    // ========================================
    // Relationships
    // ========================================
    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    // ========================================
    // Scopes
    // ========================================

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function forGym(Builder $query, int $gymId): Builder
    {
        return $query->where('gym_id', $gymId);
    }

    #[Scope]
    protected function current(Builder $query): Builder
    {
        return $query->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    #[Scope]
    protected function forCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('expense_category_id', $categoryId);
    }

    // ========================================
    // Budget Calculation Methods
    // ========================================

    /**
     * Get total expenses for this budget period
     */
    public function getSpentAmount(): float
    {
        $query = Expense::query()
            ->where('gym_id', $this->gym_id)
            ->whereIn('status', ['approved', 'paid'])
            ->whereBetween('expense_date', [$this->start_date, $this->end_date]);

        if ($this->expense_category_id) {
            $query->where('expense_category_id', $this->expense_category_id);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get remaining budget amount
     */
    public function getRemainingAmount(): float
    {
        return max(0, (float) $this->amount - $this->getSpentAmount());
    }

    /**
     * Get usage percentage
     */
    public function getUsagePercentage(): float
    {
        if ($this->amount <= 0) {
            return 0;
        }

        return min(100, round(($this->getSpentAmount() / $this->amount) * 100, 2));
    }

    /**
     * Check if budget threshold is exceeded
     */
    public function isThresholdExceeded(): bool
    {
        return $this->getUsagePercentage() >= $this->alert_threshold;
    }

    /**
     * Check if budget is exceeded (over 100%)
     */
    public function isExceeded(): bool
    {
        return $this->getSpentAmount() > $this->amount;
    }

    /**
     * Check if this budget is current (within date range)
     */
    public function isCurrent(): bool
    {
        return now()->between($this->start_date, $this->end_date);
    }

    // ========================================
    // Accessors
    // ========================================

    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => Number::currency($this->amount, in: $this->currency),
        );
    }

    protected function formattedSpentAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => Number::currency($this->getSpentAmount(), in: $this->currency),
        );
    }

    protected function formattedRemainingAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => Number::currency($this->getRemainingAmount(), in: $this->currency),
        );
    }

    /**
     * Get status color for UI
     */
    public function getStatusColor(): string
    {
        $percentage = $this->getUsagePercentage();

        return match (true) {
            $percentage >= 100 => 'danger',
            $percentage >= $this->alert_threshold => 'warning',
            $percentage >= 50 => 'info',
            default => 'success',
        };
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Create budget periods for a calendar year
     */
    public static function createYearlyBudgets(int $gymId, int $year, float $monthlyAmount, ?int $categoryId = null, string $name = 'Monthly Budget'): array
    {
        $budgets = [];

        for ($month = 1; $month <= 12; $month++) {
            $startDate = now()->setYear($year)->setMonth($month)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $budgets[] = static::create([
                'gym_id' => $gymId,
                'expense_category_id' => $categoryId,
                'name' => "{$name} - " . $startDate->format('F Y'),
                'amount' => $monthlyAmount,
                'currency' => 'INR',
                'period' => 'monthly',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'alert_threshold' => 80,
                'is_active' => true,
            ]);
        }

        return $budgets;
    }

    /**
     * Mark alert as sent
     */
    public function markAlertSent(): bool
    {
        return $this->update(['alert_sent' => true]);
    }

    /**
     * Reset alert for new period
     */
    public function resetAlert(): bool
    {
        return $this->update(['alert_sent' => false]);
    }
}
