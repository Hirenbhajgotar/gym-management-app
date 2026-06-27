<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $last_run_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 * @property-read User $user
 *
 * @method static Builder<static>|ScheduledReport newModelQuery()
 * @method static Builder<static>|ScheduledReport newQuery()
 * @method static Builder<static>|ScheduledReport query()
 * @method static Builder<static>|ScheduledReport whereCreatedAt($value)
 * @method static Builder<static>|ScheduledReport whereFilters($value)
 * @method static Builder<static>|ScheduledReport whereFrequency($value)
 * @method static Builder<static>|ScheduledReport whereGymId($value)
 * @method static Builder<static>|ScheduledReport whereId($value)
 * @method static Builder<static>|ScheduledReport whereIsActive($value)
 * @method static Builder<static>|ScheduledReport whereLastRunAt($value)
 * @method static Builder<static>|ScheduledReport whereName($value)
 * @method static Builder<static>|ScheduledReport whereRecipients($value)
 * @method static Builder<static>|ScheduledReport whereReportType($value)
 * @method static Builder<static>|ScheduledReport whereScheduledTime($value)
 * @method static Builder<static>|ScheduledReport whereTimezone($value)
 * @method static Builder<static>|ScheduledReport whereUpdatedAt($value)
 * @method static Builder<static>|ScheduledReport whereUserId($value)
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

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'filters' => 'array',
            'last_run_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
