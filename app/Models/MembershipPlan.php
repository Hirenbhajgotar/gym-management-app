<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Observers\MembershipPlanObserver;
use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gym_id
 * @property string $name
 * @property string|null $description
 * @property numeric|null $price
 * @property int|null $duration
 * @property Currency|null $currency
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 * @property-read Collection<int, GymMember> $members
 * @property-read int|null $members_count
 * @property-read Collection<int, MembershipPlan> $membershipPlans
 * @property-read int|null $membership_plans_count
 *
 * @method static MembershipPlanFactory factory($count = null, $state = [])
 * @method static Builder<static>|MembershipPlan inactive()
 * @method static Builder<static>|MembershipPlan isActive()
 * @method static Builder<static>|MembershipPlan newModelQuery()
 * @method static Builder<static>|MembershipPlan newQuery()
 * @method static Builder<static>|MembershipPlan query()
 * @method static Builder<static>|MembershipPlan whereCreatedAt($value)
 * @method static Builder<static>|MembershipPlan whereCurrency($value)
 * @method static Builder<static>|MembershipPlan whereDescription($value)
 * @method static Builder<static>|MembershipPlan whereDuration($value)
 * @method static Builder<static>|MembershipPlan whereGymId($value)
 * @method static Builder<static>|MembershipPlan whereId($value)
 * @method static Builder<static>|MembershipPlan whereIsActive($value)
 * @method static Builder<static>|MembershipPlan whereName($value)
 * @method static Builder<static>|MembershipPlan wherePrice($value)
 * @method static Builder<static>|MembershipPlan whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([MembershipPlanObserver::class])]
class MembershipPlan extends Model
{
    use HasFactory;

    public const NAVIGATION_BADGE_CACHE_KEY = 'nav_badge:membership_plan:tenant_id:';

    protected $fillable = [
        'gym_id',
        'name',
        'description',
        'price',
        'duration',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration' => 'integer',
            'is_active' => 'boolean',
            'currency' => Currency::class,
        ];
    }

    // ========================
    // RELATIONSHIPS
    // ========================
    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    /**
     * @return HasMany<GymMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(GymMember::class, 'gym_membership_plan_id');
    }

    /**
     * @return HasMany<MembershipPlan, $this>
     */
    public function membershipPlans(): HasMany
    {
        return $this->hasMany(self::class, 'gym_id');
    }

    // ========================
    // SCOPES
    // ========================

    #[Scope]
    protected function isActive($query)
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function inactive($query)
    {
        return $query->where('is_active', false);
    }
}
