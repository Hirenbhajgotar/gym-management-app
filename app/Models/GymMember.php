<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class GymMember extends Model
{
    protected $fillable = [
        'gym_id',
        'name',
        'email',
        'phone',
        'address',
        'dob',
        'membership_start',
        'membership_end',
        'membership_plan_id',
        'user_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'membership_start' => 'date',
        'membership_end' => 'date',
    ];

    protected static function booted()
    {
        static::saving(function ($member) {
            if ($member->membership_start && $member->membership_plan_id) {
                $plan = $member->membershipPlan;
                if ($plan && $plan->duration) {
                    $member->membership_end = Carbon::parse($member->membership_start)->addMonths($plan->duration);
                }
            }
        });
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function membershipPlan()
    {
        return $this->belongsTo(Plan::class, 'membership_plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // public function payments()
    // {
    //     return $this->hasMany(MemberPayment::class);
    // }
}
