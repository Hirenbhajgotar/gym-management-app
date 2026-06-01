<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $user_id
 * @property string $name
 * @property string $report_type
 * @property string $frequency
 * @property array<array-key, mixed> $recipients
 * @property string $scheduled_time
 * @property string $timezone
 * @property array<array-key, mixed>|null $filters
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereLastRunAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereRecipients($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereReportType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereScheduledTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledReport whereUserId($value)
 *
 * @mixin \Eloquent
 */
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
