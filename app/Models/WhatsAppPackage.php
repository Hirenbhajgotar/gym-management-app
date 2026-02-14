<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $message_count
 * @property float $selling_price
 * @property float $twilio_number_cost
 * @property float $twilio_msg_cost_per_unit
 * @property int $validity_days
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_popular
 * @property bool $includes_number
 * @property array|null $features
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppPackage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppPackage newQuery()
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppPackage query()
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppPackage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppPackage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppPackage whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppPackage whereMessageCount($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppPackage whereName($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppPackage whereSellingPrice($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppPackage whereSortOrder($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperWhatsAppPackage
 */
class WhatsAppPackage extends Model
{
    protected $table = 'whatsapp_packages';

    protected $fillable = [
        'name',
        'description',
        'message_count',
        'selling_price',
        'twilio_number_cost',
        'twilio_msg_cost_per_unit',
        'validity_days',
        'sort_order',
        'is_active',
        'is_popular',
        'includes_number',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'message_count' => 'integer',
            'selling_price' => 'decimal:2',
            'twilio_number_cost' => 'decimal:2',
            'twilio_msg_cost_per_unit' => 'decimal:4',
            'validity_days' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'includes_number' => 'boolean',
            'features' => 'array',
        ];
    }

    // =========================
    // Scopes
    // =========================

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }

    #[Scope]
    protected function popular(Builder $query): void
    {
        $query->where('is_popular', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('selling_price');
    }

    // =========================
    // Accessors
    // =========================

    public function yourProfit(): Attribute
    {
        return Attribute::make(
            get: function () {
                $twilioMsgCost = $this->message_count * $this->twilio_msg_cost_per_unit;
                $totalTwilioCost = $twilioMsgCost + $this->twilio_number_cost;

                return $this->selling_price - $totalTwilioCost;
            },
        );
    }

    public function marginPercentage(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->selling_price <= 0) {
                    return 0;
                }

                return ($this->your_profit / $this->selling_price) * 100;
            },
        );
    }

    public function costPerMessage(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->message_count <= 0) {
                    return 0;
                }

                return $this->selling_price / $this->message_count;
            },
        );
    }

    // =========================
    // Helper Methods
    // =========================

    public function isPopular(): bool
    {
        return $this->is_popular;
    }
}
