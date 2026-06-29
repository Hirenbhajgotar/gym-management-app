<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\WhatsAppPackageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $cost_per_message
 * @property-read mixed $margin_percentage
 * @property-read mixed $your_profit
 *
 * @method static Builder<static>|WhatsAppPackage active()
 * @method static Builder<static>|WhatsAppPackage newModelQuery()
 * @method static Builder<static>|WhatsAppPackage newQuery()
 * @method static Builder<static>|WhatsAppPackage ordered()
 * @method static Builder<static>|WhatsAppPackage popular()
 * @method static Builder<static>|WhatsAppPackage query()
 * @method static Builder<static>|WhatsAppPackage whereCreatedAt($value)
 * @method static Builder<static>|WhatsAppPackage whereDescription($value)
 * @method static Builder<static>|WhatsAppPackage whereFeatures($value)
 * @method static Builder<static>|WhatsAppPackage whereId($value)
 * @method static Builder<static>|WhatsAppPackage whereIncludesNumber($value)
 * @method static Builder<static>|WhatsAppPackage whereIsActive($value)
 * @method static Builder<static>|WhatsAppPackage whereIsPopular($value)
 * @method static Builder<static>|WhatsAppPackage whereMessageCount($value)
 * @method static Builder<static>|WhatsAppPackage whereName($value)
 * @method static Builder<static>|WhatsAppPackage whereSellingPrice($value)
 * @method static Builder<static>|WhatsAppPackage whereSortOrder($value)
 * @method static Builder<static>|WhatsAppPackage whereTwilioMsgCostPerUnit($value)
 * @method static Builder<static>|WhatsAppPackage whereTwilioNumberCost($value)
 * @method static Builder<static>|WhatsAppPackage whereUpdatedAt($value)
 * @method static Builder<static>|WhatsAppPackage whereValidityDays($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy(WhatsAppPackageObserver::class)]
class WhatsAppPackage extends Model
{
    use HasFactory;

    const NAVIGATION_BADGE_CACHE_KEY = 'whatsapp_package_navigation_badge';

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

    protected function yourProfit(): Attribute
    {
        return Attribute::make(
            get: function () {
                $twilioMsgCost = $this->message_count * $this->twilio_msg_cost_per_unit;
                $totalTwilioCost = $twilioMsgCost + $this->twilio_number_cost;

                return $this->selling_price - $totalTwilioCost;
            },
        );
    }

    protected function marginPercentage(): Attribute
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

    protected function costPerMessage(): Attribute
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
