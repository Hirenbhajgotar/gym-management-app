<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $color
 * @property bool $is_active
 * @property int $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\ExpenseCategory|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExpenseCategory> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExpenseBudget> $budgets
 */
class ExpenseCategory extends Model implements Sortable
{
    use HasFactory, LogsActivity, SortableTrait;

    protected $fillable = [
        'gym_id',
        'parent_id',
        'name',
        'description',
        'icon',
        'color',
        'is_active',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id')->orderBy('order');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

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

    // ========================================
    // Accessors & Helpers
    // ========================================

    /**
     * Get the full hierarchical path of the category
     */
    protected function getFullPathAttribute(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->parent ? $this->parent->full_path . ' > ' . $this->name : $this->name,
        );
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
     */
    public function buildSortQuery()
    {
        return static::query()->where('gym_id', $this->gym_id);
    }
}
