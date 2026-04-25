<?php

namespace App\Models;

use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $whatsapp_account_id
 * @property string $phone_number
 * @property string $phone_number_sid
 * @property string|null $channel_sender_sid WhatsApp Channel Sender SID (starts with CN)
 * @property string|null $waba_phone_number_id Meta WABA phone number ID for WABA provider
 * @property string $country_code
 * @property string|null $friendly_name
 * @property string|null $locality
 * @property string|null $region
 * @property numeric $monthly_cost
 * @property numeric $number_cost
 * @property WhatsAppPhoneNumberStatusEnum $status
 * @property WhatsAppPhoneNumberTypesEnum $type
 * @property \Illuminate\Support\Carbon|null $purchased_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\WhatsAppAccount|null $account
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereChannelSenderSid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereFriendlyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereLocality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereMonthlyCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereNumberCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber wherePhoneNumberSid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber wherePurchasedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereWhatsappAccountId($value)
 *
 * @mixin \Eloquent
 */
class WhatsAppPhoneNumber extends Model
{
    protected $table = 'whatsapp_phone_numbers';

    protected $fillable = [
        'whatsapp_account_id',
        'phone_number',
        'phone_number_sid',
        'channel_sender_sid',
        'waba_phone_number_id',
        'country_code',
        'friendly_name',
        'locality',
        'region',
        'monthly_cost',
        'number_cost',
        'status',
        'type',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'monthly_cost' => 'decimal:2',
            'number_cost' => 'decimal:2',
            'purchased_at' => 'datetime',
            'status' => WhatsAppPhoneNumberStatusEnum::class,
            'type' => WhatsAppPhoneNumberTypesEnum::class,
        ];
    }

    // =========================
    // Relationships
    // =========================

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    // =========================
    // Helper Methods
    // =========================

    public function isActive(): bool
    {
        return $this->status === WhatsAppPhoneNumberStatusEnum::ACTIVE;
    }

    public function getFormattedNumber(): string
    {
        return '+' . $this->phone_number;
    }

    /**
     * Get the Channel Sender SID (WhatsApp sender registration).
     * This is the SID from Twilio's Messaging v2 Channels Senders API (starts with XE).
     */
    public function getChannelSenderSid(): ?string
    {
        return $this->channel_sender_sid;
    }

    /**
     * Get the Incoming Phone Number SID (Twilio purchased number).
     * This is the SID from Twilio's IncomingPhoneNumbers API.
     */
    public function getIncomingPhoneNumberSid(): ?string
    {
        return $this->phone_number_sid;
    }

    /**
     * Check if the stored phone_number_sid looks like a valid IncomingPhoneNumber SID.
     * Valid SIDs start with "PN" (Phone Number).
     */
    public function isValidIncomingPhoneNumberSid(): bool
    {
        if (empty($this->phone_number_sid)) {
            return false;
        }

        return str_starts_with($this->phone_number_sid, 'PN');
    }

    /**
     * Check if the stored channel_sender_sid looks like a valid Channel Sender SID.
     * Valid SIDs start with "XE" (Channel).
     */
    public function isValidChannelSenderSid(): bool
    {
        if (empty($this->channel_sender_sid)) {
            return false;
        }

        return str_starts_with($this->channel_sender_sid, 'XE');
    }

    /**
     * Check if the legacy phone_number_sid contains a Channel Sender SID (starts with XE).
     * This helps identify records that need migration.
     */
    public function hasLegacyChannelSenderSidInPhoneNumberField(): bool
    {
        if (empty($this->phone_number_sid)) {
            return false;
        }

        return str_starts_with($this->phone_number_sid, 'XE');
    }

    /**
     * Determine if this record has proper SID separation.
     * Returns true if both SIDs are correctly stored in their respective fields.
     */
    public function hasProperSidSeparation(): bool
    {
        $phoneNumberSidValid = $this->isValidIncomingPhoneNumberSid();
        $channelSenderSidValid = $this->isValidChannelSenderSid();
        $hasLegacyIssue = $this->hasLegacyChannelSenderSidInPhoneNumberField();

        // Proper separation means:
        // - phone_number_sid is either valid PN or null/empty
        // - channel_sender_sid is either valid XE or null/empty
        // - No legacy issue where XE is in phone_number_sid field
        // return ! $hasLegacyIssue && ($phoneNumberSidValid || empty($this->phone_number_sid));

        return ! $hasLegacyIssue
           && ($phoneNumberSidValid || empty($this->phone_number_sid))
           && ($channelSenderSidValid || empty($this->channel_sender_sid));
    }
}
