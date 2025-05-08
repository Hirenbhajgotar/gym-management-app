<?php

namespace App\Models;

use App\Observers\MembershipPlanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

#[ObservedBy([MembershipPlanObserver::class])]
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

    public function gym()
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function members()
    {
        return $this->hasMany(GymMember::class, 'gym_membership_plan_id');
    }

    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            Gym::class,
            'id', // Foreign key on Gym table (gym_id in MembershipPlan)
            'id', // Foreign key on User table (owner_id in Gym)
            'gym_id', // Local key on MembershipPlan table
            'owner_id' // Local key on Gym table
        );
    }
}
