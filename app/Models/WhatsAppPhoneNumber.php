<?php

namespace App\Models;

use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $whatsapp_account_id
 * @property string $phone_number
 * @property string $phone_number_sid
 * @property string $country_code
 * @property string|null $friendly_name
 * @property string|null $locality
 * @property string|null $region
 * @property float $monthly_cost
 * @property float $number_cost
 * @property string $status
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $purchased_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\WhatsAppAccount $account
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber newModelQuery()
 * @method static \Illuminate\Database\Eloquent.Builder<static>|WhatsAppPhoneNumber newQuery()
 * @method static \Illuminate\Database\Eloquent.Builder<static>|WhatsAppPhoneNumber query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppPhoneNumber whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppPhoneNumber whereId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppPhoneNumber wherePhoneNumber($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperWhatsAppPhoneNumber
 */
class WhatsAppPhoneNumber extends Model
{
    protected $table = 'whatsapp_phone_numbers';

    protected $fillable = [
        'whatsapp_account_id',
        'phone_number',
        'phone_number_sid',
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
}
