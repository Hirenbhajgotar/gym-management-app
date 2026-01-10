<?php

namespace App\Models;

use App\Enums\ErrorCategory;
use App\Enums\ErrorPriority;
use App\Enums\ErrorStatus;
use App\Observers\ErrorReportObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $error_type
 * @property string|null $error_message
 * @property string|null $error_hash
 * @property string|null $stack_trace
 * @property array<array-key, mixed>|null $context
 * @property ErrorCategory|null $category
 * @property ErrorStatus|null $status
 * @property string|null $user_description
 * @property ErrorPriority|null $priority
 * @property int $duplicate_count
 * @property \Illuminate\Support\Carbon|null $last_occurrence
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport autoReported()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport frequent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport highPriority()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport recent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport resolved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport userReported()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereContext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereDuplicateCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereErrorHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereErrorType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereLastOccurrence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereStackTrace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereUserDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ErrorReport whereUserId($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperErrorReport
 */
#[ObservedBy([ErrorReportObserver::class])]
class ErrorReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'error_type',
        'error_message',
        'stack_trace',
        'context',
        'status',
        'user_description',
        'priority',
        'category',
        'occurred_at',
        'error_hash',
        'duplicate_count',
        'last_occurrence',
    ];

    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
        'last_occurrence' => 'datetime',
        'status' => ErrorStatus::class,
        'priority' => ErrorPriority::class,
        'category' => ErrorCategory::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', ErrorStatus::PENDING->value);
    }

    public function scopeResolved($query)
    {
        return $query->where('status', ErrorStatus::RESOLVED->value);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [ErrorPriority::HIGH->value, ErrorPriority::CRITICAL->value]);
    }

    public function scopeAutoReported($query)
    {
        return $query->where('error_type', 'auto_reported');
    }

    public function scopeUserReported($query)
    {
        return $query->where('error_type', 'user_reported');
    }

    public function scopeFrequent($query)
    {
        return $query->where('duplicate_count', '>', 5);
    }

    public function scopeRecent($query)
    {
        return $query->where('occurred_at', '>', now()->subDay());
    }

    public function toMarkdown(): string
    {
        $markdown = "### Error: {$this->error_message}\n\n";
        $markdown .= "**Type:** {$this->error_type}\n";
        $markdown .= '**Status:** ' . ($this->status?->value ?? 'N/A') . "\n";
        $markdown .= '**Priority:** ' . ($this->priority?->value ?? 'N/A') . "\n";
        $markdown .= '**Occurred:** ' . ($this->occurred_at?->toDateTimeString() ?? 'N/A') . "\n\n";

        if ($this->user) {
            $markdown .= "**User:** {$this->user->name} ({$this->user->email})\n\n";
        }

        if ($this->user_description) {
            $markdown .= "#### User Description\n{$this->user_description}\n\n";
        }

        if ($this->stack_trace) {
            $markdown .= "#### Stack Trace\n```php\n{$this->stack_trace}\n```\n\n";
        }

        if ($this->context) {
            $context = json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $markdown .= "#### Context\n```json\n{$context}\n```\n";
        }

        return $markdown;
    }
}
