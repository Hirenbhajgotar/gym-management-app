<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $message_count
 * @property numeric $selling_price
 * @property numeric $twilio_number_cost
 * @property numeric $twilio_msg_cost_per_unit
 * @property int $validity_days
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_popular
 * @property bool $includes_number
 * @property array<array-key, mixed>|null $features
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static Builder<static>|WhatsAppMessagePackage active()
 * @method static Builder<static>|WhatsAppMessagePackage newModelQuery()
 * @method static Builder<static>|WhatsAppMessagePackage newQuery()
 * @method static Builder<static>|WhatsAppMessagePackage ordered()
 * @method static Builder<static>|WhatsAppMessagePackage query()
 * @method static Builder<static>|WhatsAppMessagePackage whereCreatedAt($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereDescription($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereFeatures($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereId($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereIncludesNumber($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereIsActive($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereIsPopular($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereMessageCount($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereName($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereSellingPrice($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereSortOrder($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereTwilioMsgCostPerUnit($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereTwilioNumberCost($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereUpdatedAt($value)
 * @method static Builder<static>|WhatsAppMessagePackage whereValidityDays($value)
 * @mixin \Eloquent
 */
class WhatsAppMessagePackage extends Model
{
    protected $table = 'whatsapp_packages';

    protected $fillable = [
        'name',
        'message_count',
        'price',
        'selling_price',
        'currency',
        'description',
        'is_active',
        'sort_order',
        'twilio_number_cost',
        'twilio_msg_cost_per_unit',
        'validity_days',
        'is_popular',
        'includes_number',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'includes_number' => 'boolean',
            'price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'twilio_number_cost' => 'decimal:2',
            'twilio_msg_cost_per_unit' => 'decimal:4',
            'message_count' => 'integer',
            'validity_days' => 'integer',
            'sort_order' => 'integer',
            'features' => 'array',
        ];
    }

    #[Scope]
    protected function active(Builder $query)
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }
}
