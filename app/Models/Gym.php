<?php

namespace App\Models;

use App\Enums\DefaultRole;
use App\Observers\GymObserver;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Address|null $address
 * @property-read Collection<int, Attendances> $attendances
 * @property-read int|null $attendances_count
 * @property-read BusinessDetail|null $businessDetail
 * @property-read User|null $owner
 * @property-read Collection<int, GymMember> $gymMembers
 * @property-read int|null $gym_members_count
 * @property-read Collection<int, Lead> $leads
 * @property-read int|null $leads_count
 * @property-read Collection<int, GymMember> $members
 * @property-read int|null $members_count
 * @property-read Collection<int, MembershipPlan> $membershipPlans
 * @property-read int|null $membership_plans_count
 * @property-read Collection<int, GymSetting> $settings
 * @property-read int|null $settings_count
 * @property-read Collection<int, TenantSocialConfig> $socialConfigs
 * @property-read int|null $social_configs_count
 * @property-read Collection<int, Trainer> $trainers
 * @property-read int|null $trainers_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\GymFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gym whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperGym
 */
#[ObservedBy([GymObserver::class])]
class Gym extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'logo', 'qr_code_token'];

    // =========================
    // Relationships
    // =========================

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

    /** @return HasMany<GymMember, self> */
    public function gymMembers(): HasMany
    {
        return $this->hasMany(GymMember::class);
    }

    /** @return HasMany<MembershipPlan, self> */
    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendances::class);
    }

    public function businessDetail(): HasOne
    {
        return $this->hasOne(BusinessDetail::class);
    }

    /** @return HasMany<TenantSocialConfig, self> */
    public function socialConfigs(): HasMany
    {
        return $this->hasMany(TenantSocialConfig::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<ExpenseCategory, self> */
    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    /** @return HasMany<Expense, self> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<ExpenseBudget, self> */
    public function expenseBudgets(): HasMany
    {
        return $this->hasMany(ExpenseBudget::class);
    }

    /** @return HasMany<ReportGoal, self> */
    public function reportGoals(): HasMany
    {
        return $this->hasMany(ReportGoal::class);
    }

    /** @return HasMany<ScheduledReport, self> */
    public function scheduledReports(): HasMany
    {
        return $this->hasMany(ScheduledReport::class);
    }

    /** @return HasMany<GymSetting, self> */
    public function settings(): HasMany
    {
        return $this->hasMany(GymSetting::class);
    }

    public function whatsAppAccount(): HasOne
    {
        return $this->hasOne(WhatsAppAccount::class);
    }

    public function whatsAppTemplates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class);
    }

    public function biometricDevices(): HasMany
    {
        return $this->hasMany(BiometricDevice::class);
    }

    // =========================
    // Helper Methods
    // =========================

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

    public function activeSocialConfigs()
    {
        return $this->socialConfigs()->active();
    }

    /**
     * Get the gym owner user
     */
    public function owner()
    {
        return $this->users()
            ->wherePivot('role', DefaultRole::GYM_OWNER->value)
            ->first();
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = $this->settings()->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function getCurrentTenantUsers()
    {
        /**
         * @var Gym $currentTenant
         */
        $currentTenant = Filament::getTenant();

        if ($currentTenant) {
            return $currentTenant->users();
        }

        return User::query()->whereRaw('1 = 0'); // Return an empty query
    }

    /**
     * Get the gym's subscription through its owner
     */
    public function subscription()
    {
        return $this->owner()?->subscription();
    }

    /**
     * Check if the gym is currently on a free trial
     */
    public function onTrial(): bool
    {
        return $this->subscription()?->onTrial() ?? false;
    }

    /**
     * Check if the gym has an active subscription
     */
    public function subscribed(): bool
    {
        return $this->subscription()?->isActive() ?? false;
    }

    /**
     * Check if the gym has access to features (trial or subscription)
     */
    public function hasAccess(): bool
    {
        return $this->subscription()?->canAccessFeatures() ?? false;
    }

    /**
     * Check if the gym can use a specific feature
     */
    public function canUseFeature(string $featureName): bool
    {
        return $this->subscription()?->hasFeature($featureName) ?? false;
    }

    /**
     * Get the maximum value for a feature
     */
    public function getFeatureLimit(string $featureName): int|string|null
    {
        return $this->subscription()?->getFeatureLimit($featureName) ?? null;
    }

    // =========================
    // Accessors
    // =========================

    protected function ownerEmail(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->owner()?->email,
        );
    }

    // =========================
    // QR Code Methods
    // =========================

    /**
     * Generate or retrieve QR code token for this gym.
     */
    public function generateQrCodeToken(): string
    {
        if (empty($this->qr_code_token)) {
            $this->qr_code_token = Str::random(32);
            $this->save();
        }

        return $this->qr_code_token;
    }

    /**
     * Get the full QR code attendance URL.
     */
    public function getQrCodeUrl(): string
    {
        $token = $this->generateQrCodeToken();

        return route('attendance.qr', [
            'gymId' => $this->id,
            'token' => $token,
        ]);
    }

    /**
     * Check if QR code token is valid.
     */
    public function hasValidQrCodeToken(string $token): bool
    {
        return $this->qr_code_token === $token;
    }
}
