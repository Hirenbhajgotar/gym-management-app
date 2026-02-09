<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'user_id',
        'name',
        'report_type',
        'frequency',
        'recipients',
        'scheduled_time',
        'timezone',
        'filters',
        'last_run_at',
        'is_active',
    ];

    protected $casts = [
        'recipients' => 'array',
        'filters' => 'array',
        'last_run_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
