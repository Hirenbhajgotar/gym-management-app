<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Observers\WhatsAppAccountObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $gym_id
 * @property string|null $twilio_account_sid
 * @property string|null $twilio_auth_token
 * @property string|null $waba_id
 * @property string|null $waba_name
 * @property string|null $messaging_service_sid
 * @property string|null $verify_service_sid
 * @property string|null $meta_business_portfolio_id
 * @property string|null $meta_access_token
 * @property bool|null $embedded_signup_completed
 * @property string|null $embedded_signup_session_id
 * @property WhatsAppAccountStatusEnum|null $status
 * @property WhatsAppAccountVerificationStatusEnum|null $verification_status
 * @property string|null $business_name
 * @property string|null $business_email
 * @property string|null $business_description
 * @property WhatsAppProviderTypeEnum|null $provider_type
 * @property WhatsAppBillingModeEnum|null $billing_mode
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 * @property-read WhatsAppPhoneNumber|null $phoneNumber
 * @property-read int|null $phone_number_count
 * @property-read Collection<int, WhatsAppTemplate> $templates
 * @property-read int|null $templates_count
 *
 * @method static Builder<static>|WhatsAppAccount newModelQuery()
 * @method static Builder<static>|WhatsAppAccount newQuery()
 * @method static Builder<static>|WhatsAppAccount query()
 * @method static Builder<static>|WhatsAppAccount whereBusinessDescription($value)
 * @method static Builder<static>|WhatsAppAccount whereBusinessEmail($value)
 * @method static Builder<static>|WhatsAppAccount whereBusinessName($value)
 * @method static Builder<static>|WhatsAppAccount whereCreatedAt($value)
 * @method static Builder<static>|WhatsAppAccount whereEmbeddedSignupCompleted($value)
 * @method static Builder<static>|WhatsAppAccount whereEmbeddedSignupSessionId($value)
 * @method static Builder<static>|WhatsAppAccount whereGymId($value)
 * @method static Builder<static>|WhatsAppAccount whereId($value)
 * @method static Builder<static>|WhatsAppAccount whereMessagingServiceSid($value)
 * @method static Builder<static>|WhatsAppAccount whereMetaAccessToken($value)
 * @method static Builder<static>|WhatsAppAccount whereMetaBusinessPortfolioId($value)
 * @method static Builder<static>|WhatsAppAccount whereStatus($value)
 * @method static Builder<static>|WhatsAppAccount whereTwilioAccountSid($value)
 * @method static Builder<static>|WhatsAppAccount whereTwilioAuthToken($value)
 * @method static Builder<static>|WhatsAppAccount whereUpdatedAt($value)
 * @method static Builder<static>|WhatsAppAccount whereVerificationStatus($value)
 * @method static Builder<static>|WhatsAppAccount whereVerifiedAt($value)
 * @method static Builder<static>|WhatsAppAccount whereVerifyServiceSid($value)
 * @method static Builder<static>|WhatsAppAccount whereWabaId($value)
 * @method static Builder<static>|WhatsAppAccount whereWabaName($value)
 * @method bool isActive()
 * @method bool isVerified()
 * @method bool hasPhoneNumber()
 * @method bool isEmbeddedSignupComplete()
 * @method bool isWabaActive()
 * @method bool isWabaRestricted()
 * @method bool isSetupComplete()
 * @method bool isSelfManaged()
 * @method bool isPayViaApp()
 * @method bool hasBillingMode()
 * @method bool requiresCreditCheck()
 *
 * @mixin \Eloquent
 */
#[ObservedBy([WhatsAppAccountObserver::class])]
class WhatsAppAccount extends Model
{
    use HasFactory;

    public const NAVIGATION_BADGE_CACHE_KEY = 'whatsapp_account_navigation_badge';

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'gym_id',
        'twilio_account_sid',
        'twilio_auth_token',
        'waba_id',
        'waba_name',
        'messaging_service_sid',
        'verify_service_sid',
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
        'provider_type',
        'billing_mode',
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
            'embedded_signup_completed' => 'boolean',
            'verified_at' => 'datetime',
            'provider_type' => WhatsAppProviderTypeEnum::class,
            'billing_mode' => WhatsAppBillingModeEnum::class,
            'waba_status_synced_at' => 'datetime',
        ];
    }

    // =========================
    // Relationships
    // =========================
    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    /**
     * @return HasOne<WhatsAppPhoneNumber, $this>
     */
    public function phoneNumber(): HasOne
    {
        return $this->hasOne(WhatsAppPhoneNumber::class, 'whatsapp_account_id');
    }

    /**
     * @return HasMany<WhatsAppTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class, 'gym_id');
    }

    // =========================
    // Accessors
    // =========================

    protected function twilioAuthToken(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => (isset($attributes['twilio_auth_token']) && $attributes['twilio_auth_token'])
                ? Crypt::decrypt($attributes['twilio_auth_token'])
                : null,
            set: fn ($value) => $value ? Crypt::encrypt($value) : null,
        );
    }

    protected function metaAccessToken(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => (isset($attributes['meta_access_token']) && $attributes['meta_access_token'])
                ? Crypt::decrypt($attributes['meta_access_token'])
                : null,
            set: fn ($value) => $value ? Crypt::encrypt($value) : null,
        );
    }

    /**
     * @param  Builder<WhatsAppAccount>  $query
     */
    #[Scope]
    protected function selfManageBillingMode(Builder $query): void
    {
        $query->where('billing_mode', WhatsAppBillingModeEnum::SELF_MANAGED);
    }

    /**
     * @param  Builder<WhatsAppAccount>  $query
     */
    #[Scope]
    protected function payViaAppBillingMode(Builder $query): void
    {
        $query->where('billing_mode', WhatsAppBillingModeEnum::PAY_VIA_APP);
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

    public function isNotVerified(): bool
    {
        return $this->verification_status === WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED;
    }

    public function isInReview(): bool
    {
        return $this->verification_status === WhatsAppAccountVerificationStatusEnum::IN_REVIEW;
    }

    public function isRejected(): bool
    {
        return $this->verification_status === WhatsAppAccountVerificationStatusEnum::REJECTED;
    }

    public function isPendingReview(): bool
    {
        return $this->verification_status === WhatsAppAccountVerificationStatusEnum::PENDING;
    }

    public function hasPhoneNumber(): bool
    {
        return $this->phoneNumber()->exists();
    }

    public function hasActivePhoneNumber(): bool
    {
        return $this->phoneNumber()->active()->exists();
    }

    public function isEmbeddedSignupComplete(): bool
    {
        return $this->embedded_signup_completed === true;
    }

    public function isWabaActive(): bool
    {
        return $this->status === WhatsAppAccountStatusEnum::ACTIVE;
    }

    public function isWabaRestricted(): bool
    {
        return $this->status === WhatsAppAccountStatusEnum::RESTRICTED
            || $this->status === WhatsAppAccountStatusEnum::BANNED;
    }

    public function isWabaSuspended(): bool
    {
        return $this->status === WhatsAppAccountStatusEnum::SUSPENDED;
    }

    public function isWabaBlocked(): bool
    {
        return $this->status === WhatsAppAccountStatusEnum::BLOCKED;
    }

    public function isSetupComplete(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->isWabaProvider()) {
            return $this->waba_id !== null
                && $this->isVerified()
                && $this->hasPhoneNumber();
        }

        if ($this->isTwilioProvider()) {
            return $this->twilio_account_sid !== null
                && $this->twilio_auth_token !== null
                && $this->hasPhoneNumber();
        }

        return false;
    }

    // =========================
    // Provider Helper Methods (Per-Tenant)
    // =========================

    /**
     * Get the provider type for this account.
     * Defaults to WABA for backward compatibility.
     */
    public function getProvider(): WhatsAppProviderTypeEnum
    {
        return $this->provider_type ?? WhatsAppProviderTypeEnum::WABA;
    }

    /**
     * Check if this account uses Twilio provider.
     */
    public function isTwilioProvider(): bool
    {
        return $this->getProvider() === WhatsAppProviderTypeEnum::TWILIO;
    }

    /**
     * Check if this account uses WABA provider.
     */
    public function isWabaProvider(): bool
    {
        return $this->getProvider() === WhatsAppProviderTypeEnum::WABA;
    }

    /**
     * Check if provider type has been set.
     */
    public function hasProvider(): bool
    {
        return $this->provider_type !== null;
    }

    /**
     * Get the primary phone number ID based on provider.
     * Uses the whatsapp_phone_numbers table relationship.
     */
    public function getPhoneNumberId(): ?string
    {
        $phoneNumber = $this->phoneNumber;

        if (! $phoneNumber) {
            return null;
        }

        // For Twilio, use phone_number_sid; for WABA, could use channel_sender_sid
        return $this->isTwilioProvider()
            ? $phoneNumber->phone_number_sid
            : $phoneNumber->waba_phone_number_id;
    }

    /**
     * Check if WABA setup is required.
     * (WABA provider selected but no WABA configured yet)
     */
    public function requiresWabaSetup(): bool
    {
        return $this->isWabaProvider() && blank($this->waba_id);
    }

    /**
     * Check if Twilio setup is required.
     * (Twilio provider selected, no subaccount or no phone number)
     */
    public function requiresTwilioSetup(): bool
    {
        return $this->isTwilioProvider()
            && (blank($this->twilio_account_sid) || ! $this->hasPhoneNumber());
    }

    /**
     * Set provider type and save.
     */
    public function setProvider(WhatsAppProviderTypeEnum $provider): void
    {
        $this->update(['provider_type' => $provider]);
    }

    // =========================
    // Billing Mode Methods
    // =========================

    /**
     * Check if the account uses self-managed billing.
     * Self-managed means the user pays Meta directly, no credit check/deduction.
     */
    public function isSelfManaged(): bool
    {
        return $this->billing_mode === WhatsAppBillingModeEnum::SELF_MANAGED;
    }

    /**
     * Check if the account uses pay-via-app billing.
     * Pay-via-app means fluxfitness handles billing, credits are deducted as normal.
     */
    public function isPayViaApp(): bool
    {
        return $this->billing_mode === WhatsAppBillingModeEnum::PAY_VIA_APP;
    }

    /**
     * Check if a billing mode has been explicitly set.
     * Returns false for Twilio accounts (null billing_mode).
     */
    public function hasBillingMode(): bool
    {
        return $this->billing_mode !== null;
    }

    /**
     * Determine if credit checking is required for this account.
     * Returns true for pay-via-app, Twilio (null billing_mode), falls back to true for safety.
     * Returns false only for self-managed accounts.
     */
    public function requiresCreditCheck(): bool
    {
        if ($this->billing_mode === WhatsAppBillingModeEnum::SELF_MANAGED) {
            return false;
        }

        return true;
    }

    public function isTwilioWithActiveNumber(): bool
    {
        if (! $this->isTwilioProvider()) {
            return false;
        }

        $phoneNumber = $this->phoneNumber;

        if (! $phoneNumber) {
            return false;
        }

        return $phoneNumber->isActive();
    }

    public function isPhoneSurchargeDue(): bool
    {
        if (! $this->isTwilioWithActiveNumber()) {
            return false;
        }

        return $this->phoneNumber->isSurchargeDue();
    }
}
