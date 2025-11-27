<?php

namespace App\Models;

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
 * @property int|null $tenant_id
 * @property \App\Enums\SocialPlatform|null $platform
 * @property string|null $account_id
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
 * @property bool|null $auto_reply_enabled
 * @property bool|null $sms_acknowledgment_enabled
 * @property bool|null $email_acknowledgment_enabled
 * @property string|null $business_phone_number_id
 * @property int|null $user_id
 * @property int|null $assigned_user_id
 * @property \Illuminate\Support\Carbon|null $token_expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $masked_token
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\User|null $assignedUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SocialMediaWebhookLog> $webhookLogs
 * @property-read int|null $webhook_logs_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig byPlatform($platform)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereAdditionalConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereAppId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereAppSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereFormId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereFormName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig wherePageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig wherePageName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig wherePhoneNumberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TenantSocialConfig whereWebhookVerifyToken($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperTenantSocialConfig
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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Get the webhook logs for this social media configuration.
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(SocialMediaWebhookLog::class, 'tenant_social_config_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    public function isTokenExpired()
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function getMaskedTokenAttribute()
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
