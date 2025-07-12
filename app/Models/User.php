<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Contracts\GymUser;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active'
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn(string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        if ($panel->getId() === 'super-admin') {
            return $this->hasRole('super_admin');
        }

        if ($panel->getId() === 'admin') {
            return $this->hasActiveRole(['gym_owner', 'receptionist']);
        }

        if ($panel->getId() === 'staff') {
            return $this->hasActiveRole(['staff_member', 'receptionist']);
        }

        return false;
    }

    /**
     * Check if user has any of the given roles and is active.
     */
    private function hasActiveRole(array $roles): bool
    {
        return $this->hasAnyRole($roles) && $this->is_active;
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->gyms;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->gyms->contains($tenant);
    }

    public function gyms()
    {
        return $this->belongsToMany(Gym::class, 'gym_user')
            ->withPivot(['role', 'active'])
            ->withTimestamps();
    }

    // public function subscription()
    // {
    //     return $this->hasOne(Subscription::class);
    // }
}
