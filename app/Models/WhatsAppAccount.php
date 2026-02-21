<?php

namespace App\Models;

use App\Enums\WabaModeEnum;
use App\Enums\WabaStatusEnum;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Observers\WhatsAppAccountObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $gym_id
 * @property string $twilio_account_sid
 * @property string $twilio_auth_token
 * @property string|null $waba_id
 * @property string|null $waba_name
 * @property string|null $waba_status
 * @property string|null $waba_mode
 * @property string|null $messaging_service_sid
 * @property string|null $meta_business_portfolio_id
 * @property string|null $meta_access_token
 * @property bool $embedded_signup_completed
 * @property string|null $embedded_signup_session_id
 * @property string $status
 * @property string $verification_status
 * @property string|null $business_name
 * @property string|null $business_email
 * @property string|null $business_description
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\WhatsAppPhoneNumber $phoneNumber
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\WhatsAppTemplate[] $templates
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppAccount whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppAccount whereStatus($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperWhatsAppAccount
 */
#[ObservedBy([WhatsAppAccountObserver::class])]
class WhatsAppAccount extends Model
{
    const NAVIGATION_BADGE_CACHE_KEY = 'whatsapp_account_navigation_badge';

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'gym_id',
        'twilio_account_sid',
        'twilio_auth_token',
        'waba_id',
        'waba_name',
        'waba_status',
        'waba_mode',
        'messaging_service_sid',
        'meta_business_portfolio_id',
        'meta_access_token',
        'embedded_signup_completed',
        'embedded_signup_session_id',
        'status',
        'verification_status',
        'business_name',
        'business_email',
        'business_description',
        'verified_at',
    ];

    protected $hidden = [
        'twilio_auth_token',
        'meta_access_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsAppAccountStatusEnum::class,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::class,
            'waba_status' => WabaStatusEnum::class,
            'waba_mode' => WabaModeEnum::class,
            'embedded_signup_completed' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    // =========================
    // Relationships
    // =========================

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function phoneNumber(): HasMany
    {
        return $this->hasMany(WhatsAppPhoneNumber::class, 'whatsapp_account_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class, 'gym_id');
    }

    // =========================
    // Accessors
    // =========================

    public function twilioAuthToken(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->twilio_auth_token) {
                    return null;
                }

                return Crypt::decrypt($this->twilio_auth_token);
            },
            set: fn ($value) => $value ? Crypt::encrypt($value) : null,
        );
    }

    public function metaAccessToken(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->meta_access_token) {
                    return null;
                }

                return Crypt::decrypt($this->meta_access_token);
            },
            set: fn ($value) => $value ? Crypt::encrypt($value) : null,
        );
    }

    // =========================
    // Helper Methods
    // =========================

    public function isActive(): bool
    {
        return $this->status === WhatsAppAccountStatusEnum::ACTIVE;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === WhatsAppAccountVerificationStatusEnum::VERIFIED;
    }

    public function hasPhoneNumber(): bool
    {
        return $this->phoneNumber()->exists();
    }

    public function getPhoneNumber(): ?WhatsAppPhoneNumber
    {
        return $this->phoneNumber()->first();
    }

    public function hasIndividualWaba(): bool
    {
        return $this->waba_mode === WabaModeEnum::INDIVIDUAL;
    }

    public function hasSharedWaba(): bool
    {
        return $this->waba_mode === WabaModeEnum::SHARED;
    }

    public function hasManualWaba(): bool
    {
        return $this->waba_mode === WabaModeEnum::MANUAL;
    }

    public function isEmbeddedSignupComplete(): bool
    {
        return $this->embedded_signup_completed === true;
    }

    public function isWabaActive(): bool
    {
        return $this->waba_status === WabaStatusEnum::ACTIVE;
    }

    public function isWabaRestricted(): bool
    {
        return $this->waba_status === WabaStatusEnum::RESTRICTED
            || $this->waba_status === WabaStatusEnum::BANNED;
    }
}
