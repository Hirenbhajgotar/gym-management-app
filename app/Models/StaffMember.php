<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class StaffMember extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the gyms that this staff member is associated with.
     */
    public function gyms()
    {
        return $this->belongsToMany(Gym::class, 'gym_staff_members')
            ->withPivot('is_active')
            ->withTimestamps();
    }
    /**
     * Get the gyms that this staff member is currently active in.
     */
    public function activeGyms()
    {
        return $this->gyms()->wherePivot('is_active', true);
    }
}
