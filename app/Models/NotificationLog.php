<?php

namespace App\Models;

use App\Enums\NotificationTemplateTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $user_id
 * @property NotificationTemplateTypeEnum|null $type
 * @property string|null $channel
 * @property string|null $status
 * @property string|null $message
 * @property array<array-key, mixed>|null $response_data
 * @property string|null $external_id
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereResponseData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereUserId($value)
 * @mixin \Eloquent
 * @mixin IdeHelperNotificationLog
 */
class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'user_id',
        'type',
        'channel',
        'status',
        'message',
        'response_data',
        'external_id',
        'sent_at',
    ];

    protected $casts = [
        'response_data' => 'array',
        'sent_at' => 'datetime',
        'type' => NotificationTemplateTypeEnum::class,
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
