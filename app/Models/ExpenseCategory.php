<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FilamentColor;
use App\Observers\ExpenseCategoryObserver;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property int|null $gym_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $color
 * @property bool $is_active
 * @property bool $is_system
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, ExpenseBudget> $budgets
 * @property-read int|null $budgets_count
 * @property-read Collection<int, ExpenseCategory> $children
 * @property-read int|null $children_count
 * @property-read mixed $css_color
 * @property-read Collection<int, Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read Attribute $full_path
 * @property-read Gym|null $gym
 * @property-read ExpenseCategory|null $parent
 *
 * @method static Builder<static>|ExpenseCategory active()
 * @method static Builder<static>|ExpenseCategory availableForGym(int $gymId)
 * @method static Builder<static>|ExpenseCategory customForGym(int $gymId)
 * @method static ExpenseCategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|ExpenseCategory forGym(int $gymId)
 * @method static Builder<static>|ExpenseCategory newModelQuery()
 * @method static Builder<static>|ExpenseCategory newQuery()
 * @method static Builder<static>|ExpenseCategory ordered(string $direction = 'asc')
 * @method static Builder<static>|ExpenseCategory query()
 * @method static Builder<static>|ExpenseCategory root()
 * @method static Builder<static>|ExpenseCategory systemDefault()
 * @method static Builder<static>|ExpenseCategory whereColor($value)
 * @method static Builder<static>|ExpenseCategory whereCreatedAt($value)
 * @method static Builder<static>|ExpenseCategory whereDescription($value)
 * @method static Builder<static>|ExpenseCategory whereGymId($value)
 * @method static Builder<static>|ExpenseCategory whereIcon($value)
 * @method static Builder<static>|ExpenseCategory whereId($value)
 * @method static Builder<static>|ExpenseCategory whereIsActive($value)
 * @method static Builder<static>|ExpenseCategory whereIsSystem($value)
 * @method static Builder<static>|ExpenseCategory whereName($value)
 * @method static Builder<static>|ExpenseCategory whereOrder($value)
 * @method static Builder<static>|ExpenseCategory whereParentId($value)
 * @method static Builder<static>|ExpenseCategory whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([ExpenseCategoryObserver::class])]
class ExpenseCategory extends Model implements Sortable
{
    use HasFactory, LogsActivity, SortableTrait;

    public const NAVIGATION_BADGE_CACHE_KEY = 'nav_badge:expense_category:tenant_id:';

    protected $fillable = [
        'gym_id',
        'parent_id',
        'name',
        'description',
        'icon',
        'color',
        'is_active',
        'is_system',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'order' => 'integer',
        ];
    }

    public array $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('expense_category')
            ->logOnly(['name', 'description', 'is_active', 'parent_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => "Expense category '{$this->name}' was created",
            'updated' => "Expense category '{$this->name}' was updated",
            'deleted' => "Expense category '{$this->name}' was deleted",
            default => "Expense category '{$this->name}' was {$eventName}",
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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<ExpenseCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id')->orderBy('order');
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<ExpenseBudget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(ExpenseBudget::class);
    }

    // ========================================
    // Scopes
    // ========================================

    #[Scope]
    protected function active($query)
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function root($query)
    {
        return $query->whereNull('parent_id');
    }

    #[Scope]
    protected function forGym($query, int $gymId)
    {
        return $query->where('gym_id', $gymId);
    }

    /**
     * Scope to get only system default categories (gym_id IS NULL)
     */
    #[Scope]
    protected function systemDefault($query)
    {
        return $query->whereNull('gym_id')->where('is_system', true);
    }

    /**
     * Scope to get categories available for a gym (system defaults + gym-specific)
     * This is the main scope to use when fetching categories for display
     */
    #[Scope]
    protected function availableForGym($query, int $gymId)
    {
        return $query->where(function ($q) use ($gymId) {
            $q->whereNull('gym_id')  // System defaults
                ->orWhere('gym_id', $gymId);  // Gym-specific
        });
    }

    /**
     * Scope to get gym-specific custom categories only (excludes system defaults)
     */
    #[Scope]
    protected function customForGym($query, int $gymId)
    {
        return $query->where('gym_id', $gymId)->where('is_system', false);
    }

    // ========================================
    // Color Helpers
    // ========================================

    /**
     * Get this category's color as a CSS hex value.
     */
    protected function cssColor(): Attribute
    {
        return Attribute::make(
            get: fn () => FilamentColor::toHex($this->color ?? 'gray'),
        );
    }

    /**
     * Get the full hierarchical path of the category
     */
    protected function fullPath(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->parent ? $this->parent->full_path . ' > ' . $this->name : $this->name,
        );
    }

    /**
     * Check if category is a system default
     */
    public function isSystemDefault(): bool
    {
        return $this->is_system && is_null($this->gym_id);
    }

    /**
     * Check if category has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get total expenses amount for this category
     */
    public function getTotalExpensesAmount(): float
    {
        return (float) $this->expenses()->sum('amount');
    }

    /**
     * Build sorting query with gym scope for multi-tenancy
     * System defaults are sorted globally, gym-specific are sorted per-gym
     */
    public function buildSortQuery()
    {
        if ($this->isSystemDefault()) {
            return static::query()->whereNull('gym_id');
        }

        return static::query()->where('gym_id', $this->gym_id);
    }
}
