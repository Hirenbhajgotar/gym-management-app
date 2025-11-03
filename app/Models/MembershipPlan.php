<?php

namespace App\Models;

use App\Observers\MembershipPlanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([MembershipPlanObserver::class])]
/**
 * @property int $id
 * @property int $gym_id
 * @property string $name
 * @property string|null $description
 * @property numeric|null $price
 * @property int|null $duration
 * @property string|null $currency
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GymMember> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MembershipPlan> $membershipPlans
 * @property-read int|null $membership_plans_count
 * @method static \Database\Factories\MembershipPlanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MembershipPlan whereUpdatedAt($value)
 * @mixin \Eloquent
 * @mixin IdeHelperMembershipPlan
 */
class MembershipPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'name',
        'description',
        'price',
        'duration',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GymMember::class, 'gym_membership_plan_id');
    }

    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class, 'gym_id');
    }
}
