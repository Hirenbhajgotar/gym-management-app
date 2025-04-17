<?php

namespace App\Models;

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
        'plan_id',
        'user_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'membership_start' => 'date',
        'membership_end' => 'date',
    ];

    public function gym()
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
