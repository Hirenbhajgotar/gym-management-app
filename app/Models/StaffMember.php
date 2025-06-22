<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class StaffMember extends Authenticatable implements FilamentUser, HasTenants
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

    public function canAccessPanel(Panel $panel): bool
    {
        // Only allow access to the customer panel
        if ($panel->getId() === 'staff') {
            return true; // or add your own logic
        }

        return false;
    }

    public function getTenants(Panel $panel): array|Collection
    {
        if ($panel->getId() === 'staff') {
            return $this->gyms;
        }

        return collect();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->gyms->contains($tenant);
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
