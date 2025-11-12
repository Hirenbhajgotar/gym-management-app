<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\SubscriptionStatus;
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

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property bool|null $is_active
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Address|null $address
 * @property-read \App\Models\EmployeeDetail|null $employeeDetails
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Gym> $gyms
 * @property-read int|null $gyms_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 * @mixin \Eloquent
 * @mixin IdeHelperUser
 */
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
        "name",
        "email",
        "password",
        "phone",
        "is_active",
        "email_verified_at",
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ["password", "remember_token"];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            "email_verified_at" => "datetime",
            "password" => "hashed",
            "is_active" => "boolean",
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
            ->explode(" ")
            ->map(fn(string $name) => Str::of($name)->substr(0, 1))
            ->implode("");
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === "super-admin") {
            return $this->hasRole("super_admin");
        }

        if ($panel->getId() === "admin" || $panel->getId() === "staff") {
            // Check for tenant-specific access AND active subscription/trial
            // return $this->hasActiveTenant() && $this->canAccessFeatures(); // temporary bypass
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
        return $this->belongsToMany(Gym::class, "gym_user")
            ->withPivot(["role", "active"])
            ->withTimestamps();
    }

    public function employeeDetails(): HasOne
    {
        return $this->hasOne(EmployeeDetail::class, "user_id");
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, "addressable");
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->active()->whereDate("current_period_end", ">=", now());
    }

    public function subscription()
    {
        return Subscription::active()
            ->where("user_id", $this->id)
            ->whereDate("current_period_end", ">=", now())
            ->first();
    }

    // Subscription-based access control methods
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->active()
            ->whereDate("current_period_end", ">=", now())
            ->exists();
    }

    public function canAccessFeatures(): bool
    {
        return $this->subscription()?->canAccessFeatures() ?? false;
    }

    public function getSubscriptionPlan()
    {
        return $this->subscription()?->plan;
    }

    public function getFeatureLimit(string $featureName): int|string|null
    {
        return $this->subscription()?->getFeatureLimit($featureName) ?? null;
    }

    public function canUseFeature(
        string $featureName,
        int $currentUsage = 0,
    ): bool {
        return $this->subscription()?->canUseFeature(
            $featureName,
            $currentUsage,
        );
    }

    public function hasUsedTrial(): bool
    {
        return Subscription::where("user_id", $this->id)
            ->where("status", "!=", SubscriptionStatus::TRIAL)
            ->whereNotNull("trial_ends_at")
            ->where("trial_ends_at", "<=", now())
            ->exists();
    }

    public function getMaxGyms(): int
    {
        $limit = $this->getFeatureLimit("max_gyms");

        return $limit === null || $limit === "unlimited"
            ? PHP_INT_MAX
            : (int) $limit;
    }

    public function canCreateMoreGyms(): bool
    {
        if (!$this->canAccessFeatures()) {
            return false;
        }

        return $this->gyms()->count() < $this->getMaxGyms();
    }

    /**
     * Get all subscription history for this user
     */
    public function subscriptionHistory()
    {
        return $this->subscriptions()
            ->with(['plan', 'features'])
            ->latest()
            ->get();
    }
}
