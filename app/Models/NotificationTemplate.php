<?php

namespace App\Models;

use App\Enums\NotificationTemplateTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $gym_owner_id
 * @property NotificationTemplateTypeEnum|null $type
 * @property string|null $channel
 * @property string|null $subject
 * @property string|null $email_template
 * @property string|null $whatsapp_template
 * @property int|null $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\User|null $gymOwner
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereEmailTemplate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereGymOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTemplate whereWhatsappTemplate($value)
 * @mixin \Eloquent
 * @mixin IdeHelperNotificationTemplate
 */
class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'gym_owner_id',
        'type',
        'channel',
        'subject',
        'email_template',
        'whatsapp_template',
        'is_active',
    ];

    protected $casts = [
        'type' => NotificationTemplateTypeEnum::class,
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function gymOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gym_owner_id');
    }

    public function parseTemplate(array $variables): string
    {
        $template = $this->channel === 'email' ? $this->email_template : $this->whatsapp_template;

        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        return $template;
    }
}
