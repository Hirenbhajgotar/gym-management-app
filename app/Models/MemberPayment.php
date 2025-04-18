<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberPayment extends Model
{
    protected $fillable = [
        'member_id',
        'membership_plan_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'notes',
        'payment_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(GymMember::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }
}
