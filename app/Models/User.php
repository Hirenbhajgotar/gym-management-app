<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasName, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

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
        'is_active',
        'email_verified_at',
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

    public function getFilamentName(): string
    {
        return $this->name;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        if ($panel->getId() === 'super-admin') {
            return $this->hasRole('super_admin');
        }

        if ($panel->getId() === 'admin' || $panel->getId() === 'staff') {
            // Then check for tenant-specific access
            return $this->hasActiveTenant();
        }

        return false;
    }

    private function hasActiveTenant(): bool
    {
        return $this->gyms->isNotEmpty() && $this->is_active;
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
        // Check if user is associated with this gym through pivot table
        return $this->gyms->contains($tenant);
    }

    public function gyms()
    {
        return $this->belongsToMany(Gym::class, 'gym_user')
            ->withPivot(['role', 'active'])
            ->withTimestamps();
    }

    public function employeeDetails(): HasOne
    {
        return $this->hasOne(EmployeeDetail::class, 'user_id');
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to_user_id');
    }

    // public function subscription()
    // {
    //     return $this->hasOne(Subscription::class);
    // }
}
