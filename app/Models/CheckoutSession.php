<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\CheckoutSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $token
 * @property int|null $user_id
 * @property string $type
 * @property int|null $plan_id
 * @property int|null $subscription_id
 * @property string $billing_cycle
 * @property numeric|null $amount
 * @property Currency $currency
 * @property string $status
 * @property string|null $razorpay_order_id
 * @property numeric|null $razorpay_order_amount
 * @property Carbon|null $expires_at
 * @property array<array-key, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Plan|null $plan
 * @property-read Subscription|null $subscription
 *
 * @method static Builder<static>|CheckoutSession newModelQuery()
 * @method static Builder<static>|CheckoutSession newQuery()
 * @method static Builder<static>|CheckoutSession query()
 *
 * @mixin \Eloquent
 */
class CheckoutSession extends Model
{
    /** @use HasFactory<CheckoutSessionFactory> */
    use HasFactory;

    public const TYPE_NEW = 'new';

    public const TYPE_UPGRADE = 'upgrade';

    public const TYPE_RENEWAL = 'renewal';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'token',
        'user_id',
        'type',
        'plan_id',
        'subscription_id',
        'billing_cycle',
        'amount',
        'currency',
        'status',
        'razorpay_order_id',
        'razorpay_order_amount',
        'expires_at',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'razorpay_order_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'metadata' => 'array',
            'currency' => Currency::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @param  Builder<CheckoutSession>  $query
     * @return Builder<CheckoutSession>
     */
    #[Scope]
    protected function valid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('expires_at', '>', now());
    }

    public static function generateToken(): string
    {
        return Str::random(40);
    }
}
