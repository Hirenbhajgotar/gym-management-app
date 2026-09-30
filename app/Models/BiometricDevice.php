<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Observers\BiometricDeviceObserver;
use Database\Factories\BiometricDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * @property int $id
 * @property int $gym_id
 * @property string $device_name
 * @property string|null $device_serial
 * @property string $device_type
 * @property BiometricProvidersEnum|null $provider_type
 * @property array<array-key, mixed>|null $provider_config
 * @property BiometricDeviceStatusEnum $status
 * @property BiometricDevicePurpose $purpose
 * @property bool $adms_enabled
 * @property int|null $assigned_staff_id
 * @property Carbon|null $last_heartbeat_at
 * @property string|null $device_token
 * @property string|null $token_hash
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $assignedStaff
 * @property-read User $createdBy
 * @property-read Collection<int, DoorEvent> $doorEvents
 * @property-read int|null $door_events_count
 * @property-read Gym $gym
 * @property-read Collection<int, BiometricTemplate> $templates
 * @property-read int|null $templates_count
 *
 * @method static Builder<static>|BiometricDevice active()
 * @method static BiometricDeviceFactory factory($count = null, $state = [])
 * @method static Builder<static>|BiometricDevice forGym(int $gymId)
 * @method static Builder<static>|BiometricDevice newModelQuery()
 * @method static Builder<static>|BiometricDevice newQuery()
 * @method static Builder<static>|BiometricDevice query()
 * @method static Builder<static>|BiometricDevice whereAssignedStaffId($value)
 * @method static Builder<static>|BiometricDevice whereCreatedAt($value)
 * @method static Builder<static>|BiometricDevice whereCreatedBy($value)
 * @method static Builder<static>|BiometricDevice whereDeviceName($value)
 * @method static Builder<static>|BiometricDevice whereDeviceSerial($value)
 * @method static Builder<static>|BiometricDevice whereDeviceToken($value)
 * @method static Builder<static>|BiometricDevice whereDeviceType($value)
 * @method static Builder<static>|BiometricDevice whereGymId($value)
 * @method static Builder<static>|BiometricDevice whereId($value)
 * @method static Builder<static>|BiometricDevice whereLastHeartbeatAt($value)
 * @method static Builder<static>|BiometricDevice whereProviderConfig($value)
 * @method static Builder<static>|BiometricDevice whereProviderType($value)
 * @method static Builder<static>|BiometricDevice wherePurpose($value)
 * @method static Builder<static>|BiometricDevice whereStatus($value)
 * @method static Builder<static>|BiometricDevice whereTokenHash($value)
 * @method static Builder<static>|BiometricDevice whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([BiometricDeviceObserver::class])]
class BiometricDevice extends Model
{
    use HasFactory;

    public const NAVIGATION_BADGE_CACHE_KEY = 'nav_badge:biometric_device:tenant_id:';

    protected $fillable = [
        'gym_id',
        'device_name',
        'device_serial',
        'device_type',
        'provider_type',
        'provider_config',
        'status',
        'is_enabled',
        'purpose',
        'adms_enabled',
        'assigned_staff_id',
        'last_heartbeat_at',
        'device_token',
        'token_hash',
        'adms_comm_key_hash',
        'adms_comm_key_salt',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array{
     *     provider_config: 'encrypted:array',
     *     last_heartbeat_at: 'datetime',
     *     device_token: 'hashed',
     *     status: 'App\Enums\BiometricDeviceStatusEnum',
     *     is_enabled: 'boolean',
     *     provider_type: 'App\Enums\BiometricProvidersEnum',
     *     purpose: 'App\Enums\BiometricDevicePurpose',
     *     adms_enabled: 'boolean',
     * }
     */
    protected function casts(): array
    {
        return [
            'provider_config' => 'encrypted:array',
            'last_heartbeat_at' => 'datetime',
            'device_token' => 'hashed',
            'status' => BiometricDeviceStatusEnum::class,
            'is_enabled' => 'boolean',
            'provider_type' => BiometricProvidersEnum::class,
            'purpose' => BiometricDevicePurpose::class,
            'adms_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<BiometricTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class, 'enrolled_by_device_id');
    }

    /**
     * @return HasMany<DoorEvent, $this>
     */
    public function doorEvents(): HasMany
    {
        return $this->hasMany(DoorEvent::class, 'device_id');
    }

    /**
     * @return HasMany<AdmsDeviceCommand, $this>
     */
    public function admsCommands(): HasMany
    {
        return $this->hasMany(AdmsDeviceCommand::class, 'device_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', BiometricDeviceStatusEnum::Active)
            ->where('is_enabled', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forGym(Builder $query, int $gymId): void
    {
        $query->where('gym_id', $gymId);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function admsEnabled(Builder $query): void
    {
        $query->where('adms_enabled', true);
    }

    /**
     * O(1) Serial Number lookup with Redis caching for ADMS devices.
     */
    public static function findBySerial(string $serial): ?self
    {
        if (blank($serial) || strlen($serial) > 64) {
            return null;
        }

        $cacheKey = "biometric:device:serial:{$serial}";
        $cachedId = Cache::get($cacheKey);

        if ($cachedId !== null && $cachedId > 0) {
            /** @var self|null $cachedDevice */
            $cachedDevice = self::query()->find($cachedId);
            if ($cachedDevice && $cachedDevice->device_serial === $serial && $cachedDevice->is_enabled && $cachedDevice->adms_enabled) {
                return $cachedDevice;
            }

            Cache::forget($cacheKey);
        }

        /** @var self|null $device */
        $device = self::query()
            ->where('device_serial', $serial)
            ->where('adms_enabled', true)
            ->where('is_enabled', true)
            ->first();

        if ($device) {
            Cache::put($cacheKey, $device->id, 86400);

            return $device;
        }

        return null;
    }

    public function verifyToken(string $plainToken): bool
    {
        return $this->device_token !== null && Hash::check($plainToken, $this->device_token);
    }

    /**
     * O(1) Token seek via SHA-256 digest with Redis caching and legacy fallback.
     */
    public static function findByPlainToken(string $plainToken): ?self
    {
        if (blank($plainToken)) {
            return null;
        }

        $tokenHash = hash('sha256', $plainToken);
        $cacheKey = "biometric:device:token:{$tokenHash}";

        // Fast-path: check cache for device ID
        $cachedId = Cache::get($cacheKey);
        if ($cachedId === 0) {
            return null;
        }

        if ($cachedId !== null) {
            /** @var self|null $cachedDevice */
            $cachedDevice = self::query()->find($cachedId);
            if ($cachedDevice && $cachedDevice->token_hash === $tokenHash) {
                return $cachedDevice;
            }

            Cache::forget($cacheKey);
        }

        // Indexed search by token_hash
        /** @var self|null $device */
        $device = self::query()
            ->where('token_hash', $tokenHash)
            ->first();

        if ($device) {
            Cache::put($cacheKey, $device->id, 86400);

            return $device;
        }

        // Backward compatibility fallback for legacy devices without token_hash populated.
        // Bounded and cached to protect against unbounded bcrypt workloads (Should-Fix 1).
        $hasLegacyDevices = Cache::remember('biometric:devices:has_legacy_tokens', 3600, function (): bool {
            return self::query()->whereNull('token_hash')->whereNotNull('device_token')->exists();
        });

        if ($hasLegacyDevices) {
            $legacyDevices = self::query()
                ->whereNull('token_hash')
                ->whereNotNull('device_token')
                ->orderBy('id')
                ->limit(10)
                ->get();

            foreach ($legacyDevices as $legacy) {
                if ($legacy->verifyToken($plainToken)) {
                    // Auto-upgrade legacy device to indexed token_hash!
                    $legacy->update(['token_hash' => $tokenHash]);
                    Cache::forget('biometric:devices:has_legacy_tokens');
                    Cache::put($cacheKey, $legacy->id, 86400);

                    return $legacy;
                }
            }
        }

        return null;
    }

    /**
     * Check if this device has an ADMS pushcommkey configured (either via hash or provider_config).
     */
    public function hasCommKeyConfigured(): bool
    {
        if (filled($this->adms_comm_key_hash)) {
            return true;
        }

        $rawKey = is_array($this->provider_config)
            ? ($this->provider_config['pushcommkey'] ?? $this->provider_config['comm_key'] ?? null)
            : null;

        return filled($rawKey);
    }

    public static function missCounterCacheKey(int|string $deviceId): string
    {
        return "biometric_device:consecutive_misses:{$deviceId}";
    }

    public static function generateCommKeySalt(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function hashCommKey(string $plainKey, string $salt): string
    {
        $iterations = max(1, config()->integer('biometrics.adms.pbkdf2_iterations', 100000));

        return hash_pbkdf2('sha256', $plainKey, $salt, $iterations);
    }

    /**
     * Verify the provided plain pushcommkey against this device's verifier without mutating state.
     */
    public function verifyCommKey(string $plainKey): bool
    {
        if (filled($this->adms_comm_key_hash)) {
            if (filled($this->adms_comm_key_salt)) {
                return hash_equals(
                    $this->adms_comm_key_hash,
                    self::hashCommKey($plainKey, $this->adms_comm_key_salt)
                );
            }

            // Legacy fallback for unsalted SHA-256 records prior to PBKDF2 migration
            return hash_equals($this->adms_comm_key_hash, hash('sha256', $plainKey));
        }

        $rawKey = is_array($this->provider_config)
            ? ($this->provider_config['pushcommkey'] ?? $this->provider_config['comm_key'] ?? null)
            : null;
        $expectedKey = (is_string($rawKey) || is_numeric($rawKey)) ? (string) $rawKey : null;

        if (filled($expectedKey)) {
            return hash_equals($expectedKey, $plainKey);
        }

        return false;
    }

    public function isActive(): bool
    {
        if (array_key_exists('is_enabled', $this->attributes) && ! $this->attributes['is_enabled']) {
            return false;
        }

        return $this->status === BiometricDeviceStatusEnum::Active;
    }

    public function isDeviceNative(): bool
    {
        return blank($this->provider_type)
            || $this->provider_type === BiometricProvidersEnum::DeviceNative;
    }

    public function hasCloudProvider(): bool
    {
        return filled($this->provider_type)
            && $this->provider_type !== BiometricProvidersEnum::DeviceNative;
    }

    public function isAttendanceOnly(): bool
    {
        return $this->purpose === BiometricDevicePurpose::AttendanceOnly;
    }

    public function isDoorAccess(): bool
    {
        return in_array($this->purpose, [
            BiometricDevicePurpose::DoorAccess,
            BiometricDevicePurpose::DoorAccessNoAttendance,
        ], true);
    }

    public function shouldCreateAttendance(): bool
    {
        return in_array($this->purpose, [
            BiometricDevicePurpose::AttendanceOnly,
            BiometricDevicePurpose::DoorAccess,
        ], true);
    }
}
