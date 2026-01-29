<?php

namespace App\Models;

use App\Enums\NotificationAccountStatusEnum;
use App\Enums\NotificationVerificationStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $gym_owner_id
 * @property string|null $whatsapp_business_number
 * @property string|null $whatsapp_api_key
 * @property string|null $whatsapp_instance_id
 * @property string|null $whatsapp_provider
 * @property array<array-key, mixed>|null $notification_types
 * @property string|null $twilio_account_sid
 * @property string|null $twilio_auth_token
 * @property string|null $twilio_api_key
 * @property string|null $twilio_api_secret
 * @property NotificationAccountStatusEnum|null $account_status
 * @property NotificationVerificationStatusEnum|null $verification_status
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property array<array-key, mixed>|null $twilio_metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\User|null $gymOwner
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereAccountStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereGymOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereNotificationTypes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTwilioAccountSid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTwilioApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTwilioApiSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTwilioAuthToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTwilioMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereVerificationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereWhatsappApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereWhatsappBusinessNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereWhatsappInstanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereWhatsappProvider($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperNotificationSetting
 */
class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'gym_owner_id',
        'whatsapp_business_number',
        'whatsapp_api_key',
        'whatsapp_instance_id',
        'whatsapp_provider',
        'notification_types',
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_api_key',
        'twilio_api_secret',
        'account_status',
        'verification_status',
        'verified_at',
        'twilio_metadata',
        'waba_sid',
        'whatsapp_sender_sid',
        'phone_number_sid',
        'verification_sid',
    ];

    protected $casts = [
        'notification_types' => 'array',
        'twilio_metadata' => 'array',
        'verified_at' => 'datetime',
        'account_status' => NotificationAccountStatusEnum::class,
        'verification_status' => NotificationVerificationStatusEnum::class,
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function gymOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gym_owner_id');
    }

    public function isNotificationEnabled(string $type, string $channel = 'whatsapp'): bool
    {
        $types = $this->notification_types ?? [];

        return isset($types[$type]) && in_array($channel, $types[$type] ?? []);
    }

    public function isAccountActive(): bool
    {
        return $this->account_status === NotificationAccountStatusEnum::ACTIVE;
    }

    public function isAccountVerified(): bool
    {
        return $this->verification_status === NotificationVerificationStatusEnum::VERIFIED;
    }

    protected function twilioAccountSid(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? encrypt($value) : null,
            get: fn ($value) => $value ? decrypt($value) : null,
        );
    }

    protected function twilioAuthToken(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? encrypt($value) : null,
            get: fn ($value) => $value ? decrypt($value) : null,
        );
    }

    protected function wabaSid(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? encrypt($value) : null,
            get: fn ($value) => $value ? decrypt($value) : null,
        );
    }

    protected function whatsappSenderSid(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? encrypt($value) : null,
            get: fn ($value) => $value ? decrypt($value) : null,
        );
    }

    protected function phoneNumberSid(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? encrypt($value) : null,
            get: fn ($value) => $value ? decrypt($value) : null,
        );
    }

    protected function metaVerificationLink(): Attribute
    {
        return Attribute::make(
            get: fn () => config('app.meta_verification_link') . $this->waba_sid,
        );
    }
}
