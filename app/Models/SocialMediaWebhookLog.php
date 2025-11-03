<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_social_config_id
 * @property string|null $webhook_id
 * @property string $platform
 * @property array<array-key, mixed> $payload
 * @property array<array-key, mixed>|null $response
 * @property string $status
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TenantSocialConfig|null $socialConfig
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereTenantSocialConfigId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialMediaWebhookLog whereWebhookId($value)
 * @mixin \Eloquent
 * @mixin IdeHelperSocialMediaWebhookLog
 */
class SocialMediaWebhookLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'social_media_webhook_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_social_config_id',
        'webhook_id',
        'platform',
        'payload',
        'response',
        'status',
        'error_message',
        'processed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the social media configuration that owns this log.
     */
    public function socialConfig(): BelongsTo
    {
        return $this->belongsTo(TenantSocialConfig::class, 'tenant_social_config_id');
    }
}
