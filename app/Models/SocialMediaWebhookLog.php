<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
