<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $gym_id
 * @property string $template_name
 * @property string $template_sid
 * @property string|null $language
 * @property array<array-key, mixed>|null $variables
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $synced_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereTemplateName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereTemplateSid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsappTemplate whereVariables($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperWhatsappTemplate
 */
class WhatsappTemplate extends Model
{
    protected $fillable = [
        'gym_id',
        'template_name',
        'template_sid',
        'language',
        'variables',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Get the gym that owns the template
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    /**
     * Check if template is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
