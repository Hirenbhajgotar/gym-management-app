<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

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
    ];

    protected $casts = [
        'additional_config' => 'array',
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'app_secret',
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
