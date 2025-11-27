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
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Gym> $gyms
 * @property-read int|null $gyms_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffMember withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 * @mixin IdeHelperStaffMember
 */
class StaffMember extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, HasRoles, Notifiable;

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
