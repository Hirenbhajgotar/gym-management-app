<?php

namespace App\Models;

use App\Enums\ErrorCategory;
use App\Enums\ErrorPriority;
use App\Enums\ErrorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
