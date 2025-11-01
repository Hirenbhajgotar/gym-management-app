<?php

namespace App\Models;

use App\Observers\GymObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

#[ObservedBy([GymObserver::class])]
class Gym extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gym_user')
            ->withPivot(['role', 'active'])
            ->withTimestamps();
    }

    public function members(): HasMany
    {
        return $this->hasMany(GymMember::class);
    }

    public function trainers(): HasMany
    {
        return $this->hasMany(Trainer::class);
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public static function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (self::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /** @return HasMany<\App\Models\GymMember, self> */
    public function gymMembers(): HasMany
    {
        return $this->hasMany(\App\Models\GymMember::class);
    }

    /** @return HasMany<\App\Models\MembershipPlan, self> */
    public function membershipPlans(): HasMany
    {
        return $this->hasMany(\App\Models\MembershipPlan::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendances::class);
    }

    public function businessDetail(): HasOne
    {
        return $this->hasOne(BusinessDetail::class);
    }

    public function socialConfigs(): HasMany
    {
        return $this->hasMany(TenantSocialConfig::class);
    }

    public function activeSocialConfigs()
    {
        return $this->socialConfigs()->active();
    }

    public function getOwnerAttribute()
    {
        return $this->users()->wherePivot('role', \App\Enums\DefaultRole::GYM_OWNER->value)->first();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public static function getCurrentTenantUsers()
    {
        $currentTenant = \Filament\Facades\Filament::getTenant();

        if ($currentTenant) {
            return $currentTenant->users();
        }

        return User::query()->whereRaw('1 = 0'); // Return an empty query
    }

    public function settings(): HasMany
    {
        return $this->hasMany(GymSetting::class);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = $this->settings()->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Get the gym's subscription through its owner
     */
    public function subscription()
    {
        return $this->user?->subscription();
    }

    /**
     * Check if the gym is currently on a free trial
     */
    public function onTrial(): bool
    {
        return $this->subscription() && $this->subscription->onTrial();
    }

    /**
     * Check if the gym has an active subscription
     */
    public function subscribed(): bool
    {
        return $this->subscription() && $this->subscription->isActive();
    }

    /**
     * Check if the gym has access to features (trial or subscription)
     */
    public function hasAccess(): bool
    {
        return $this->subscription() && $this->subscription->canAccessFeatures();
    }

    /**
     * Check if the gym can use a specific feature
     */
    public function canUseFeature(string $featureName): bool
    {
        return $this->subscription() && $this->subscription->hasFeature($featureName);
    }

    /**
     * Get the maximum value for a feature
     */
    public function getFeatureLimit(string $featureName): int|string|null
    {
        return $this->subscription() ? $this->subscription->getFeatureLimit($featureName) : null;
    }
}
