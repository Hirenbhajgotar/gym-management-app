<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\DefaultRole;
use App\Jobs\ReleaseTenantTwilioResources;
use App\Observers\GymObserver;
use App\Traits\AssertsTransactionalAuditSink;
use Database\Factories\GymFactory;
use Exception;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LaraZeus\QrCode\Generator as QrGenerator;
use RuntimeException;

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
 */
#[ObservedBy([GymObserver::class])]
class Gym extends Model
{
    use AssertsTransactionalAuditSink;
    use HasFactory;

    protected $fillable = ['name', 'slug', 'logo'];

    /**
     * Set only for the duration of an audited owner purge that has already exported the
     * outstanding erasure register to a durable, non-tenant-scoped table.
     */
    protected bool $erasureRegisterExported = false;

    protected static function booted(): void
    {
        // The observer implements `ShouldHandleEventsAfterCommit`, so its cascade (and any
        // guard there) runs after the row and its database cascade are gone and can never
        // refuse. This listener runs synchronously, before the DELETE, so it aborts the
        // transaction and performs the one external cleanup that must happen while the
        // WhatsApp account still exists.
        static::deleting(static function (self $gym): void {
            $gym->assertCanBeDeleted($gym->erasureRegisterExported);
            $gym->cleanupTwilioResourcesBeforeDeletion();
        });
    }

    /**
     * Wrap the model delete so the synchronous observer cascade cannot half-destroy a
     * tenant when a later relation delete throws.
     *
     * The panel row action and the owner purge both call this; a nested call is a
     * savepoint, so the purge's outer transaction still owns the unit of work.
     */
    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }

    /**
     * Delegate the quiet variant to the audited path.
     *
     * `Model::deleteQuietly()` runs `withoutEvents()`, so it would skip the biometric
     * guard, the observer cascade and the Twilio capture. Query-builder deletes
     * (`Gym::query()->delete()`) still bypass model events; callers must not use them for
     * this model.
     */
    public function deleteQuietly(): bool
    {
        return $this->delete() === true;
    }

    /**
     * Refuse to delete a gym that still owns biometric terminals or erasure obligations.
     *
     * The foreign keys cascade, so a gym delete destroys its devices, door events, receipts,
     * command ledger and templates at the database level and skips the model `delete()`, the
     * credential tombstone, the erasure obligations and the deletion audit. Refusing while
     * either a terminal or an outstanding obligation exists makes the audited per-device
     * path the only route; an operator must retire each terminal and discharge its
     * obligations before the tenant can be removed.
     *
     * The automated owner purge is the one exception, and only after it has exported the
     * register to {@see BiometricErasureRegisterExport}: an owner with a terminal cannot
     * retire it without creating the obligation that would otherwise refuse the delete
     * forever.
     */
    public function assertCanBeDeleted(bool $erasureRegisterExported = false): void
    {
        $this->assertHasNoBiometricDevices();
        $this->assertHasNoOutstandingErasureObligations($erasureRegisterExported);
    }

    public function assertHasNoBiometricDevices(): void
    {
        if (! $this->biometricDevices()->exists()) {
            return;
        }

        throw new RuntimeException(
            "Cannot delete gym {$this->id}: it still has biometric terminals. Deleting them destroys their access-control history and skips the erasure obligations; delete each terminal from Device Management first."
        );
    }

    public function assertHasNoOutstandingErasureObligations(bool $erasureRegisterExported = false): void
    {
        if ($erasureRegisterExported || ! $this->hasOutstandingErasureObligations()) {
            return;
        }

        throw new RuntimeException(
            "Cannot delete gym {$this->id}: it still has biometric erasure obligations. Deleting the gym would destroy the register of credentials that have not been confirmed erased; discharge each obligation before deleting the tenant."
        );
    }

    /**
     * Whether the operator surface would show at least one outstanding obligation.
     *
     * The guard and the operator surface must read the same predicate: reading only the
     * flag let a flagless `DeletionUnresolvable` row or a cloud `PendingDeletion` row pass
     * the guard while the tenant cascade destroyed the register.
     */
    public function hasOutstandingErasureObligations(): bool
    {
        $candidates = $this->outstandingErasureObligationCandidates();

        if ($candidates->isEmpty()) {
            return false;
        }

        $exhaustedHardwareDeleteKeys = BiometricTemplate::exhaustedHardwareDeleteKeys($this->id);

        return $candidates->contains(
            fn (BiometricTemplate $template): bool => $template->requiresOperatorAttention($exhaustedHardwareDeleteKeys)
        );
    }

    /**
     * @return Collection<int, BiometricTemplate>
     */
    public function outstandingErasureObligationCandidates(): Collection
    {
        return $this->biometricTemplates()
            ->withTrashed()
            ->where(function (Builder $query): void {
                $query->where('is_erasure_obligation', true)
                    ->orWhereIn('sync_status', BiometricTemplateSyncStatusEnum::operatorAttentionCandidateStates());
            })
            ->get();
    }

    /**
     * Retire every terminal through the audited per-device path.
     *
     * The owner-purge command must not bypass the terminal delete: the gym cascade skips the
     * credential tombstone, the ledger terminalisation and the deletion audit. Each device
     * is deleted individually so a failure stops the purge with a clear message instead of
     * destroying evidence.
     */
    public function retireBiometricTerminals(): void
    {
        foreach ($this->biometricDevices()->get() as $device) {
            if ($device->delete() !== true) {
                throw new RuntimeException(
                    "Cannot retire biometric terminal {$device->id} for gym {$this->id}; the owner purge is blocked so the deletion cannot bypass the audit."
                );
            }
        }
    }

    /**
     * Write a durable, non-tenant-scoped copy of the outstanding erasure register.
     *
     * The export has no foreign key to this gym, so it survives the tenant cascade. The
     * activity entry is written in the same transaction and fails closed: an export that
     * cannot be audited must not be used to justify deleting the register.
     */
    public function exportErasureRegister(?User $actor = null): BiometricErasureRegisterExport
    {
        $this->assertAuditSinkIsTransactional();

        return DB::transaction(function () use ($actor): BiometricErasureRegisterExport {
            $obligations = $this->outstandingErasureObligationCandidates()
                ->map(fn (BiometricTemplate $template): array => [
                    'template_id' => $template->getKey(),
                    'gym_member_id' => $template->gym_member_id,
                    'enrolled_by_device_id' => $template->enrolled_by_device_id,
                    'biometric_type' => $template->biometric_type?->value,
                    'provider_type' => $template->provider_type,
                    'sync_status' => $template->sync_status?->value,
                    'is_erasure_obligation' => (bool) $template->is_erasure_obligation,
                    'created_at' => $template->created_at?->toIso8601String(),
                    'deleted_at' => $template->deleted_at?->toIso8601String(),
                ])
                ->values()
                ->all();

            $export = BiometricErasureRegisterExport::query()->create([
                'gym_id' => $this->id,
                'gym_name' => $this->name,
                'obligation_count' => count($obligations),
                'obligations' => $obligations,
                'exported_at' => now(),
            ]);

            $activityLogger = activity('biometric')
                ->performedOn($this)
                ->withProperties([
                    'gym_id' => $this->id,
                    'gym_name' => $this->name,
                    'export_id' => $export->getKey(),
                    'obligation_count' => count($obligations),
                ]);

            if ($actor !== null) {
                $activityLogger->causedBy($actor);
            }

            if ($activityLogger->log('Biometric erasure register exported before tenant deletion') === null) {
                throw new RuntimeException(
                    'The tenant was not deleted because the exported erasure register could not be audited.'
                );
            }

            return $export;
        });
    }

    /**
     * Audited owner-purge delete: export the register, then delete the tenant.
     *
     * This is deliberately a separate method rather than a silent bypass on `delete()`:
     * only the automated purge, which has no operator to discharge the obligations it
     * creates by retiring the terminals, may use it.
     */
    public function deleteWithExportedErasureRegister(?User $actor = null): bool
    {
        return DB::transaction(function () use ($actor): bool {
            $this->exportErasureRegister($actor);
            $this->erasureRegisterExported = true;

            try {
                return $this->delete() === true;
            } finally {
                $this->erasureRegisterExported = false;
            }
        });
    }

    /**
     * Capture the tenant's Twilio resources for a release after commit.
     *
     * Releasing them here was wrong in both directions: a later rollback could not restore
     * a released subaccount, and `cleanupTenantResources()` reports partial failure by
     * returning `['success' => false]`, which the old caller discarded before deleting the
     * local rows. The intent (including the encrypted subaccount token) is written to a
     * table with no tenant foreign key, and a queued job releases it after commit. A failed
     * release leaves the intent row for the recovery sweep.
     */
    public function cleanupTwilioResourcesBeforeDeletion(): void
    {
        // Explicit load: `preventLazyLoading` is enabled outside production, and the gym
        // instance reaching the delete path is not eager-loaded with this relation.
        $this->loadMissing('whatsAppAccount.phoneNumber');

        $account = $this->whatsAppAccount;

        if ($account === null) {
            return;
        }

        $phoneNumber = $account->phoneNumber;

        $release = WhatsAppAccountRelease::query()->create([
            'gym_id' => $this->id,
            'twilio_account_sid' => $account->twilio_account_sid,
            'twilio_auth_token' => $account->twilio_auth_token,
            'messaging_service_sid' => $account->messaging_service_sid,
            'phone_number' => $phoneNumber?->phone_number,
            'channel_sender_sid' => $phoneNumber?->channel_sender_sid,
            'incoming_phone_sid' => $phoneNumber?->getIncomingPhoneNumberSid(),
            'status' => WhatsAppAccountRelease::STATUS_PENDING,
        ]);

        ReleaseTenantTwilioResources::dispatch($release->getKey())->afterCommit();
    }

    // =========================
    // Relationships
    // =========================
    /**
     * @return BelongsToMany<User, $this, GymUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gym_user')
            ->using(GymUser::class)
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

    /**
     * @return HasMany<BiometricTemplate, $this>
     */
    public function biometricTemplates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class);
    }

    /**
     * Determine if the gym has at least one active and enabled biometric device.
     */
    public function hasActiveBiometricDevice(): bool
    {
        return $this->biometricDevices()->active()->exists();
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
        return is_string($this->qr_code_token) && $this->qr_code_token !== '' && hash_equals($this->qr_code_token, $token);
    }

    /**
     * Get the SVG markup for the check-in QR code.
     */
    public function getCheckInQrCodeSvg(int $size = 240): ?string
    {
        if (blank($this->qr_code_token)) {
            return null;
        }

        return Cache::remember("gym_checkin_qr_svg_{$this->id}_{$this->qr_code_token}_{$size}", now()->addDay(), function () use ($size): string {
            $qr = (new QrGenerator())
                ->size($size)
                ->margin(1)
                ->generate($this->getCheckInQrCodeUrl());

            return $qr instanceof Htmlable
                ? $qr->toHtml()
                : (is_string($qr) ? $qr : '');
        });
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
        return is_string($this->qr_code_checkout_token) && $this->qr_code_checkout_token !== '' && hash_equals($this->qr_code_checkout_token, $token);
    }

    /**
     * Get the SVG markup for the checkout QR code.
     */
    public function getCheckoutQrCodeSvg(int $size = 240): ?string
    {
        if (blank($this->qr_code_checkout_token)) {
            return null;
        }

        return Cache::remember("gym_checkout_qr_svg_{$this->id}_{$this->qr_code_checkout_token}_{$size}", now()->addDay(), function () use ($size): string {
            $qr = (new QrGenerator())
                ->size($size)
                ->margin(1)
                ->generate($this->getCheckoutQrCodeUrl());

            return $qr instanceof Htmlable
                ? $qr->toHtml()
                : (is_string($qr) ? $qr : '');
        });
    }
}
