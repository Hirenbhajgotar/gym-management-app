<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DefaultRole;
use App\Observers\GymObserver;
use Database\Factories\GymFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property string|null $qr_code_token
 * @property string|null $qr_code_checkout_token
 * @property string|null $logo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Address|null $address
 * @property-read Collection<int, Attendances> $attendances
 * @property-read int|null $attendances_count
 * @property-read Collection<int, BiometricDevice> $biometricDevices
 * @property-read int|null $biometric_devices_count
 * @property-read BusinessDetail|null $businessDetail
 * @property-read Collection<int, ExpenseBudget> $expenseBudgets
 * @property-read int|null $expense_budgets_count
 * @property-read Collection<int, ExpenseCategory> $expenseCategories
 * @property-read int|null $expense_categories_count
 * @property-read Collection<int, Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read Collection<int, GymMember> $gymMembers
 * @property-read int|null $gym_members_count
 * @property-read Collection<int, Lead> $leads
 * @property-read int|null $leads_count
 * @property-read Collection<int, GymMember> $members
 * @property-read int|null $members_count
 * @property-read Collection<int, MembershipPlan> $membershipPlans
 * @property-read int|null $membership_plans_count
 * @property-read mixed $owner_email
 * @property-read Collection<int, ReportGoal> $reportGoals
 * @property-read int|null $report_goals_count
 * @property-read Collection<int, ScheduledReport> $scheduledReports
 * @property-read int|null $scheduled_reports_count
 * @property-read Collection<int, GymSetting> $settings
 * @property-read int|null $settings_count
 * @property-read Collection<int, TenantSocialConfig> $socialConfigs
 * @property-read int|null $social_configs_count
 * @property-read Collection<int, Trainer> $trainers
 * @property-read int|null $trainers_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read WhatsAppAccount|null $whatsAppAccount
 * @property-read Collection<int, WhatsAppTemplate> $whatsAppTemplates
 * @property-read int|null $whats_app_templates_count
 *
 * @method static GymFactory factory($count = null, $state = [])
 * @method static Builder<static>|Gym newModelQuery()
 * @method static Builder<static>|Gym newQuery()
 * @method static Builder<static>|Gym query()
 * @method static Builder<static>|Gym whereCreatedAt($value)
 * @method static Builder<static>|Gym whereId($value)
 * @method static Builder<static>|Gym whereLogo($value)
 * @method static Builder<static>|Gym whereName($value)
 * @method static Builder<static>|Gym whereQrCodeCheckoutToken($value)
 * @method static Builder<static>|Gym whereQrCodeToken($value)
 * @method static Builder<static>|Gym whereSlug($value)
 * @method static Builder<static>|Gym whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperGym
 */
#[ObservedBy([GymObserver::class])]
class Gym extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'logo', 'qr_code_token', 'qr_code_checkout_token'];

    // =========================
    // Relationships
    // =========================
    /**
     * @return BelongsToMany<User, $this, Pivot>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gym_user')
            ->withPivot(['role', 'active'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<GymMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(GymMember::class);
    }

    /**
     * @return HasMany<Trainer, $this>
     */
    public function trainers(): HasMany
    {
        return $this->hasMany(Trainer::class);
    }

    /**
     * @return MorphOne<Address, $this>
     */
    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    /** @return HasMany<GymMember, $this> */
    public function gymMembers(): HasMany
    {
        return $this->hasMany(GymMember::class);
    }

    /** @return HasMany<MembershipPlan, $this> */
    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    /**
     * @return HasMany<Attendances, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendances::class);
    }

    /**
     * @return HasOne<BusinessDetail, $this>
     */
    public function businessDetail(): HasOne
    {
        return $this->hasOne(BusinessDetail::class);
    }

    /** @return HasMany<TenantSocialConfig, $this> */
    public function socialConfigs(): HasMany
    {
        return $this->hasMany(TenantSocialConfig::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<ExpenseCategory, $this> */
    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<ExpenseBudget, $this> */
    public function expenseBudgets(): HasMany
    {
        return $this->hasMany(ExpenseBudget::class);
    }

    /** @return HasMany<ReportGoal, $this> */
    public function reportGoals(): HasMany
    {
        return $this->hasMany(ReportGoal::class);
    }

    /** @return HasMany<ScheduledReport, $this> */
    public function scheduledReports(): HasMany
    {
        return $this->hasMany(ScheduledReport::class);
    }

    /** @return HasMany<GymSetting, $this> */
    public function settings(): HasMany
    {
        return $this->hasMany(GymSetting::class);
    }

    /**
     * @return HasOne<WhatsAppAccount, $this>
     */
    public function whatsAppAccount(): HasOne
    {
        return $this->hasOne(WhatsAppAccount::class);
    }

    /**
     * @return HasMany<WhatsAppTemplate, $this>
     */
    public function whatsAppTemplates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class);
    }

    /**
     * @return HasMany<BiometricDevice, $this>
     */
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

        while (self::query()->where('slug', $slug)->exists()) {
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
    public function owner(): ?User
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
    // CheckIn QR Code Methods
    // =========================

    /**
     * Generate or retrieve QR code token for this gym.
     */
    public function generateCheckInQrCodeToken(): string
    {
        if (blank($this->qr_code_token)) {
            $this->qr_code_token = Str::random(32);
            $this->save();
        }

        return $this->qr_code_token;
    }

    /**
     * Get the full QR code attendance URL.
     */
    public function getCheckInQrCodeUrl(): string
    {
        $token = $this->generateCheckInQrCodeToken();

        return route('attendance.qr.checkin', [
            'gymId' => $this->id,
            'token' => $token,
        ]);
    }

    /**
     * Check if QR code token is valid.
     */
    public function hasValidCheckInQrCodeToken(string $token): bool
    {
        return $this->qr_code_token === $token;
    }

    // =========================
    // Checkout QR Code Methods
    // =========================

    /**
     * Generate or retrieve QR code token for checkout.
     */
    public function generateCheckoutQrCodeToken(): string
    {
        if (blank($this->qr_code_checkout_token)) {
            $this->qr_code_checkout_token = Str::random(32);
            $this->save();
        }

        return $this->qr_code_checkout_token;
    }

    /**
     * Get the full QR code checkout attendance URL.
     */
    public function getCheckoutQrCodeUrl(): string
    {
        $token = $this->generateCheckoutQrCodeToken();

        return route('attendance.qr.checkout', [
            'gymId' => $this->id,
            'token' => $token,
        ]);
    }

    /**
     * Check if checkout QR code token is valid.
     */
    public function hasValidCheckoutQrCodeToken(string $token): bool
    {
        return $this->qr_code_checkout_token === $token;
    }
}
