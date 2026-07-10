<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DefaultRole;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
use App\Enums\RecurringFrequency;
use App\Observers\ExpenseObserver;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $expense_category_id
 * @property int $user_id
 * @property int|null $approved_by_id
 * @property string $title
 * @property string|null $description
 * @property numeric $amount
 * @property string $currency
 * @property Carbon|null $expense_date
 * @property ExpensePaymentMethod $payment_method
 * @property ExpenseStatus $status
 * @property string|null $vendor_name
 * @property string|null $vendor_contact
 * @property string|null $reference_number
 * @property array<array-key, mixed>|null $attachments
 * @property array<array-key, mixed>|null $metadata
 * @property bool|null $is_recurring
 * @property RecurringFrequency|null $recurring_frequency
 * @property Carbon|null $next_due_date
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User|null $approvedBy
 * @property-read ExpenseCategory|null $category
 * @property-read mixed $formatted_amount
 * @property-read Gym $gym
 * @property-read User $user
 *
 * @method static Builder<static>|Expense approved()
 * @method static Builder<static>|Expense draft()
 * @method static Builder<static>|Expense dueSoon(int $days = 7)
 * @method static ExpenseFactory factory($count = null, $state = [])
 * @method static Builder<static>|Expense forGym(int $gymId)
 * @method static Builder<static>|Expense forUser(int $userId)
 * @method static Builder<static>|Expense inPeriod($startDate, $endDate)
 * @method static Builder<static>|Expense newModelQuery()
 * @method static Builder<static>|Expense newQuery()
 * @method static Builder<static>|Expense paid()
 * @method static Builder<static>|Expense pending()
 * @method static Builder<static>|Expense query()
 * @method static Builder<static>|Expense recurring()
 * @method static Builder<static>|Expense rejected()
 * @method static Builder<static>|Expense whereAmount($value)
 * @method static Builder<static>|Expense whereApprovedAt($value)
 * @method static Builder<static>|Expense whereApprovedById($value)
 * @method static Builder<static>|Expense whereAttachments($value)
 * @method static Builder<static>|Expense whereCreatedAt($value)
 * @method static Builder<static>|Expense whereCurrency($value)
 * @method static Builder<static>|Expense whereDescription($value)
 * @method static Builder<static>|Expense whereExpenseCategoryId($value)
 * @method static Builder<static>|Expense whereExpenseDate($value)
 * @method static Builder<static>|Expense whereGymId($value)
 * @method static Builder<static>|Expense whereId($value)
 * @method static Builder<static>|Expense whereIsRecurring($value)
 * @method static Builder<static>|Expense whereMetadata($value)
 * @method static Builder<static>|Expense whereNextDueDate($value)
 * @method static Builder<static>|Expense wherePaidAt($value)
 * @method static Builder<static>|Expense wherePaymentMethod($value)
 * @method static Builder<static>|Expense whereRecurringFrequency($value)
 * @method static Builder<static>|Expense whereReferenceNumber($value)
 * @method static Builder<static>|Expense whereRejectedAt($value)
 * @method static Builder<static>|Expense whereRejectionReason($value)
 * @method static Builder<static>|Expense whereStatus($value)
 * @method static Builder<static>|Expense whereSubmittedAt($value)
 * @method static Builder<static>|Expense whereTitle($value)
 * @method static Builder<static>|Expense whereUpdatedAt($value)
 * @method static Builder<static>|Expense whereUserId($value)
 * @method static Builder<static>|Expense whereVendorContact($value)
 * @method static Builder<static>|Expense whereVendorName($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([ExpenseObserver::class])]
class Expense extends Model
{
    use HasFactory, LogsActivity;

    public const NAVIGATION_BADGE_CACHE_KEY = 'expense_navigation_badge_count_';

    protected $fillable = [
        'gym_id',
        'expense_category_id',
        'user_id',
        'approved_by_id',
        'title',
        'description',
        'amount',
        'currency',
        'expense_date',
        'payment_method',
        'status',
        'vendor_name',
        'vendor_contact',
        'reference_number',
        'attachments',
        'metadata',
        'is_recurring',
        'recurring_frequency',
        'next_due_date',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'payment_method' => ExpensePaymentMethod::class,
            'status' => ExpenseStatus::class,
            'attachments' => 'array',
            'metadata' => 'array',
            'is_recurring' => 'boolean',
            'recurring_frequency' => RecurringFrequency::class,
            'next_due_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Default currency and gym-owner auto-approval.
     *
     * Applied on the model so it always runs synchronously before insert,
     * independent of any observer, allowing ExpenseObserver to defer its
     * side effects (cache invalidation / broadcasts) until after commit.
     */
    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            if (blank($expense->currency)) {
                $expense->currency = 'INR';
            }

            if ($expense->user_id && $expense->shouldAutoApprove()) {
                $expense->status = ExpenseStatus::APPROVED;
                $expense->approved_by_id = $expense->user_id;
                $expense->approved_at = now();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('expense')
            ->logOnly([
                'title',
                'amount',
                'status',
                'expense_category_id',
                'approved_by_id',
                'rejection_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $amount = Number::currency((float) $this->amount, in: $this->currency);

        return match ($eventName) {
            'created' => "Expense '{$this->title}' of {$amount} was created",
            'updated' => "Expense '{$this->title}' of {$amount} was updated",
            'deleted' => "Expense '{$this->title}' of {$amount} was deleted",
            default => "Expense '{$this->title}' was {$eventName}",
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    // ========================================
    // Scopes
    // ========================================

    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', ExpenseStatus::DRAFT);
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', ExpenseStatus::PENDING);
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', ExpenseStatus::APPROVED);
    }

    #[Scope]
    protected function rejected(Builder $query): void
    {
        $query->where('status', ExpenseStatus::REJECTED);
    }

    #[Scope]
    protected function paid(Builder $query): void
    {
        $query->where('status', ExpenseStatus::PAID);
    }

    #[Scope]
    protected function forGym(Builder $query, int $gymId): void
    {
        $query->where('gym_id', $gymId);
    }

    #[Scope]
    protected function forUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    #[Scope]
    protected function recurring(Builder $query): void
    {
        $query->where('is_recurring', true);
    }

    #[Scope]
    protected function dueSoon(Builder $query, int $days = 7): void
    {
        $query->recurring()
            ->whereNotNull('next_due_date')
            ->whereBetween('next_due_date', [now(), now()->addDays($days)]);
    }

    #[Scope]
    protected function inPeriod(Builder $query, $startDate, $endDate): void
    {
        $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    // ========================================
    // Status Helpers
    // ========================================

    public function isDraft(): bool
    {
        return $this->status === ExpenseStatus::DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === ExpenseStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === ExpenseStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === ExpenseStatus::REJECTED;
    }

    public function isPaid(): bool
    {
        return $this->status === ExpenseStatus::PAID;
    }

    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    public function canSubmit(): bool
    {
        return $this->status === ExpenseStatus::DRAFT;
    }

    public function canApprove(): bool
    {
        return $this->status === ExpenseStatus::PENDING;
    }

    // ========================================
    // Actions
    // ========================================

    /**
     * Submit expense for approval
     */
    public function submit(): bool
    {
        if (! $this->canSubmit()) {
            return false;
        }

        return $this->update([
            'status' => ExpenseStatus::PENDING,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Approve the expense
     */
    public function approve(User $approver): bool
    {
        if (! $this->canApprove()) {
            return false;
        }

        return $this->update([
            'status' => ExpenseStatus::APPROVED,
            'approved_by_id' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Reject the expense
     */
    public function reject(User $rejector, string $reason): bool
    {
        if (! $this->canApprove()) {
            return false;
        }

        return $this->update([
            'status' => ExpenseStatus::REJECTED,
            'approved_by_id' => $rejector->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Mark expense as paid
     */
    public function markAsPaid(): bool
    {
        if ($this->status !== ExpenseStatus::APPROVED) {
            return false;
        }

        return $this->update([
            'status' => ExpenseStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    /**
     * Cancel the expense
     */
    public function cancel(): bool
    {
        if ($this->status->isFinalized()) {
            return false;
        }

        return $this->update([
            'status' => ExpenseStatus::CANCELLED,
        ]);
    }

    // ========================================
    // Accessors
    // ========================================

    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => Number::currency((float) $this->amount, in: $this->currency),
        );
    }

    /**
     * Check if the creator should have auto-approval
     */
    public function shouldAutoApprove(): bool
    {
        // Gym owners get auto-approval
        return $this->user->hasRole(DefaultRole::GYM_OWNER->value);
    }

    /**
     * Update next due date for recurring expense
     */
    public function updateNextDueDate(): void
    {
        if ($this->is_recurring && $this->recurring_frequency) {
            $this->update([
                'next_due_date' => $this->recurring_frequency->getNextDueDate($this->expense_date),
            ]);
        }
    }
}
