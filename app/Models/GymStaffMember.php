<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Gym|null $gym
 * @property-read \App\Models\StaffMember|null $staffMember
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymStaffMember active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymStaffMember inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymStaffMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymStaffMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymStaffMember query()
 * @mixin \Eloquent
 * @mixin IdeHelperGymStaffMember
 */
class GymStaffMember extends Model
{
    protected $table = 'gym_staff_members';

    protected $guarded = [];

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}
