<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $gym_id
 * @property SocialPlatform|null $platform
 * @property string|null $page_id
 * @property string|null $page_name
 * @property string|null $form_id
 * @property string|null $form_name
 * @property string|null $phone_number_id
 * @property string|null $phone_number
 * @property string|null $access_token
 * @property string|null $app_id
 * @property string|null $app_secret
 * @property string|null $webhook_verify_token
 * @property array<array-key, mixed>|null $additional_config
 * @property bool $is_active
 * @property int|null $auto_reply_enabled
 * @property int|null $sms_acknowledgment_enabled
 * @property int|null $email_acknowledgment_enabled
 * @property string|null $sms_template
 * @property string|null $email_template
 * @property int|null $reply_delay_minutes
 * @property int|null $debug_mode
 * @property int|null $rate_limit
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $assignedUser
 * @property-read mixed $masked_token
 * @property-read Gym $gym
 * @property-read Collection<int, SocialMediaWebhookLog> $webhookLogs
 * @property-read int|null $webhook_logs_count
 *
 * @method static Builder<static>|TenantSocialConfig active()
 * @method static Builder<static>|TenantSocialConfig byPlatform($platform)
 * @method static Builder<static>|TenantSocialConfig newModelQuery()
 * @method static Builder<static>|TenantSocialConfig newQuery()
 * @method static Builder<static>|TenantSocialConfig query()
 * @method static Builder<static>|TenantSocialConfig whereAccessToken($value)
 * @method static Builder<static>|TenantSocialConfig whereAdditionalConfig($value)
 * @method static Builder<static>|TenantSocialConfig whereAppId($value)
 * @method static Builder<static>|TenantSocialConfig whereAppSecret($value)
 * @method static Builder<static>|TenantSocialConfig whereAutoReplyEnabled($value)
 * @method static Builder<static>|TenantSocialConfig whereCreatedAt($value)
 * @method static Builder<static>|TenantSocialConfig whereDebugMode($value)
 * @method static Builder<static>|TenantSocialConfig whereEmailAcknowledgmentEnabled($value)
 * @method static Builder<static>|TenantSocialConfig whereEmailTemplate($value)
 * @method static Builder<static>|TenantSocialConfig whereFormId($value)
 * @method static Builder<static>|TenantSocialConfig whereFormName($value)
 * @method static Builder<static>|TenantSocialConfig whereGymId($value)
 * @method static Builder<static>|TenantSocialConfig whereId($value)
 * @method static Builder<static>|TenantSocialConfig whereIsActive($value)
 * @method static Builder<static>|TenantSocialConfig wherePageId($value)
 * @method static Builder<static>|TenantSocialConfig wherePageName($value)
 * @method static Builder<static>|TenantSocialConfig wherePhoneNumber($value)
 * @method static Builder<static>|TenantSocialConfig wherePhoneNumberId($value)
 * @method static Builder<static>|TenantSocialConfig wherePlatform($value)
 * @method static Builder<static>|TenantSocialConfig whereRateLimit($value)
 * @method static Builder<static>|TenantSocialConfig whereReplyDelayMinutes($value)
 * @method static Builder<static>|TenantSocialConfig whereSmsAcknowledgmentEnabled($value)
 * @method static Builder<static>|TenantSocialConfig whereSmsTemplate($value)
 * @method static Builder<static>|TenantSocialConfig whereTokenExpiresAt($value)
 * @method static Builder<static>|TenantSocialConfig whereUpdatedAt($value)
 * @method static Builder<static>|TenantSocialConfig whereWebhookVerifyToken($value)
 *
 * @mixin \Eloquent
 */
class TenantSocialConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'platform',
        'page_id',
        'page_name',
        'form_id',
        'form_name',
        'phone_number_id',
        'phone_number',
        'access_token',
        'app_id',
        'app_secret',
        'webhook_verify_token',
        'additional_config',
        'is_active',
        'token_expires_at',
        'assigned_user_id',
        'auto_reply_enabled',
        'sms_acknowledgment_enabled',
        'email_acknowledgment_enabled',
        'sms_template',
        'email_template',
        'reply_delay_minutes',
        'debug_mode',
        'rate_limit',
    ];

    protected $casts = [
        'additional_config' => 'array',
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
        'platform' => SocialPlatform::class,
    ];

    // Encrypt sensitive data
    protected function accessToken(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    protected function appSecret(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Get the webhook logs for this social media configuration.
     * @return HasMany<SocialMediaWebhookLog, $this>
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(SocialMediaWebhookLog::class, 'tenant_social_config_id');
    }

    protected function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    public function isTokenExpired()
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    protected function getMaskedTokenAttribute()
    {
        if (! $this->access_token) {
            return null;
        }

        $token = $this->access_token;
        if (strlen($token) > 10) {
            return substr($token, 0, 6) . '...' . substr($token, -4);
        }

        return '***';
    }
}
