<?php

namespace App\Models;

use App\Observers\MembershipPlanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([MembershipPlanObserver::class])]
class MembershipPlan extends Model
{
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
}
