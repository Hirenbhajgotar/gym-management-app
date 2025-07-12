<?php

namespace App\Models;

use App\Contracts\GymUser;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class StaffMember extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, Notifiable, HasRoles;

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

    // Set the guard name for Spatie Permission
    protected string $guard_name = 'staff';

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

    // public function canManageGym(int $gymId): bool
    // {
    //     return $this->gyms()->where('gyms.id', $gymId)->exists();
    // }

    // public function hasRole(string $role): bool
    // {
    //     // If using Spatie Permission, this method is already available
    //     // If using custom roles, implement your logic here
    //     return parent::hasRole($role);
    // }

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
