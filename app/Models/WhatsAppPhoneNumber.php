<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Helpers\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $whatsapp_account_id
 * @property string|null $phone_number
 * @property string|null $phone_number_sid
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
 * @property Carbon|null $purchased_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsAppAccount|null $account
 * @property-read Carbon|null $last_number_cost_charged_at
 *
 * @method static Builder<static>|WhatsAppPhoneNumber newModelQuery()
 * @method static Builder<static>|WhatsAppPhoneNumber newQuery()
 * @method static Builder<static>|WhatsAppPhoneNumber query()
 * @method static Builder<static>|WhatsAppPhoneNumber whereChannelSenderSid($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereCountryCode($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereCreatedAt($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereFriendlyName($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereId($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereLocality($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereMonthlyCost($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereNumberCost($value)
 * @method static Builder<static>|WhatsAppPhoneNumber wherePhoneNumber($value)
 * @method static Builder<static>|WhatsAppPhoneNumber wherePhoneNumberSid($value)
 * @method static Builder<static>|WhatsAppPhoneNumber wherePurchasedAt($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereRegion($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereStatus($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereType($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereUpdatedAt($value)
 * @method static Builder<static>|WhatsAppPhoneNumber whereWhatsappAccountId($value)
 *
 * @mixin \Eloquent
 */
class WhatsAppPhoneNumber extends Model
{
    use HasFactory;

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
        'last_number_cost_charged_at',
    ];

    protected function casts(): array
    {
        return [
            'monthly_cost' => 'decimal:2',
            'number_cost' => 'decimal:2',
            'purchased_at' => 'datetime',
            'last_number_cost_charged_at' => 'datetime',
            'status' => WhatsAppPhoneNumberStatusEnum::class,
            'type' => WhatsAppPhoneNumberTypesEnum::class,
        ];
    }

    // =========================
    // Accessors & Mutators
    // =========================

    /**
     * Interact with the phone number attribute.
     * Formats and stores the phone number with '+' prefix and country code.
     * No spaces or extra characters (e.g. +919261212121, +15551231234).
     *
     * @return Attribute<?string, ?string>
     */
    protected function phoneNumber(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => $this->formatPhoneNumber($value),
            set: fn (?string $value, array $attributes) => $this->formatPhoneNumber($value),
        );
    }

    /**
     * Format a phone number to standard format (+<country_code><number>).
     * Removes spaces, dashes, brackets, and extra characters.
     */
    protected function formatPhoneNumber(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (str_starts_with($value, '+')) {
            $digits = preg_replace('/\D/', '', $value) ?? '';

            return $digits !== '' ? '+' . $digits : null;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        $countryCode = $this->country_code ?? config()->string('app.default_country_iso', 'IN');
        $ccDigits = '';

        if (filled($countryCode)) {
            $countryCodeStr = trim($countryCode);

            if (ctype_alpha($countryCodeStr) && strlen($countryCodeStr) === 2) {
                $ccDigits = PhoneNumber::getIntlCallingCode($countryCodeStr) ?? '';
            } else {
                $ccDigits = preg_replace('/\D/', '', $countryCodeStr) ?? '';
            }

            if ($ccDigits !== '' && ! str_starts_with($digits, $ccDigits)) {
                $digits = $ccDigits . $digits;
            }
        }

        if (strlen($digits) === 10) {
            $defaultCc = preg_replace('/\D/', '', config()->string('app.default_country_code', '91')) ?? '';
            if ($defaultCc !== '' && ! str_starts_with($digits, $defaultCc)) {
                $digits = $defaultCc . $digits;
            }
        }

        return '+' . $digits;
    }

    // =========================
    // Relationships
    // =========================
    /**
     * @return BelongsTo<WhatsAppAccount, $this>
     */
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
        if (blank($this->phone_number_sid)) {
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
        if (blank($this->channel_sender_sid)) {
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
        if (blank($this->phone_number_sid)) {
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
           && ($phoneNumberSidValid || blank($this->phone_number_sid))
           && ($channelSenderSidValid || blank($this->channel_sender_sid));
    }

    public function isSurchargeDue(): bool
    {
        if ($this->last_number_cost_charged_at === null) {
            return true;
        }

        return $this->last_number_cost_charged_at->lte(now()->subDays(config('services.twilio.number_surcharge_days', 30)));
    }

    public function getSurchargeAmount(): float
    {
        if ($this->number_cost === null || (float) $this->number_cost <= 0) {
            return config('services.twilio.number_cost', 136.41);
        }

        return (float) $this->number_cost;
    }
}
