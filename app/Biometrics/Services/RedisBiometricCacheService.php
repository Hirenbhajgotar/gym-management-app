<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Models\GymMember;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisBiometricCacheService
{
    public const DEFAULT_TTL_SECONDS = 3600; // 1 hour

    /**
     * Retrieve cached member access summary or warm it from DB.
     *
     * @return array{
     *     id: int,
     *     gym_id: int,
     *     name: string,
     *     can_access: bool,
     *     denial_reason: string|null,
     *     is_frozen: bool,
     *     payment_status: string|null,
     *     membership_end: string|null,
     *     has_consent: bool
     * }|null
     */
    public function getMemberAccessSummary(int $gymId, int|string $identifier): ?array
    {
        if (blank($identifier)) {
            return null;
        }

        $cacheKey = $this->buildMemberKey($gymId, $identifier);

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            // Re-evaluate expiry timestamp in-memory without DB query
            $rawEnd = $cached['membership_end'] ?? null;
            if ($cached['can_access'] && filled($rawEnd) && (is_string($rawEnd) || is_numeric($rawEnd))) {
                $end = Date::parse((string) $rawEnd);
                if ($end->isPast()) {
                    $cached['can_access'] = false;
                    $cached['denial_reason'] = 'membership_expired';
                }
            }

            /** @var array{id: int, gym_id: int, name: string, can_access: bool, denial_reason: string|null, is_frozen: bool, payment_status: string|null, membership_end: string|null, has_consent: bool} $cached */
            return $cached;
        }

        // Cache miss: Load member from DB if identifier is a valid numeric ID
        if (! is_numeric($identifier)) {
            return null;
        }

        /** @var GymMember|null $member */
        $member = GymMember::query()
            ->where('gym_id', $gymId)
            ->where('id', (int) $identifier)
            ->with(['biometricConsents'])
            ->first();

        if (! $member) {
            return null;
        }

        $summary = $this->buildSummaryFromModel($member);

        $this->putMemberSummary($gymId, $summary);

        return $summary;
    }

    /**
     * Atomically acquire anti-passback lock using Redis SET NX EX with an owner token.
     */
    public function acquireAntiPassbackLock(int $gymId, int $memberId, ?int $windowSeconds = null, ?string &$ownerToken = null): bool
    {
        $defaultWindow = config()->integer('biometrics.anti_passback_window_seconds', 60);
        $seconds = $windowSeconds ?? $defaultWindow;
        $lockKey = "biometric:fraud:{$gymId}:{$memberId}";
        $token = bin2hex(random_bytes(16));

        try {
            /** @var PhpRedisConnection|PredisConnection $redis */
            $redis = Redis::connection();

            $acquired = (bool) $redis->set($lockKey, $token, ['EX' => $seconds, 'NX']);
            if ($acquired) {
                $ownerToken = $token;

                return true;
            }

            return false;
        } catch (Throwable) {
            // Fallback to cache store if direct Redis driver isn't available
            if (Cache::add($lockKey, $token, $seconds)) {
                $ownerToken = $token;

                return true;
            }

            return false;
        }
    }

    /**
     * Release anti-passback lock safely using compare-and-delete when ownerToken is provided.
     */
    public function releaseAntiPassbackLock(int $gymId, int $memberId, ?string $ownerToken = null): bool
    {
        $lockKey = "biometric:fraud:{$gymId}:{$memberId}";

        if ($ownerToken !== null) {
            try {
                /** @var PhpRedisConnection|PredisConnection $redis */
                $redis = Redis::connection();
                $lua = 'if redis.call("get", KEYS[1]) == ARGV[1] then return redis.call("del", KEYS[1]) else return 0 end';

                $evalResult = $redis->eval($lua, 1, $lockKey, $ownerToken);

                return (is_numeric($evalResult) ? (int) $evalResult : 0) > 0;
            } catch (Throwable) {
                if (Cache::get($lockKey) === $ownerToken) {
                    return Cache::forget($lockKey);
                }

                return false;
            }
        }

        // Unconditional release (used for checkout or testing cleanup)
        try {
            /** @var PhpRedisConnection|PredisConnection $redis */
            $redis = Redis::connection();

            if ((int) $redis->del($lockKey) > 0) {
                return true;
            }
        } catch (Throwable) {
        }

        return Cache::forget($lockKey);
    }

    /**
     * Invalidate member cache when subscription, status, or consent changes.
     */
    public function invalidateMember(int $gymId, int $memberId): void
    {
        Cache::forget($this->buildMemberKey($gymId, $memberId));
    }

    /**
     * Store summary in cache.
     *
     * @param array{
     *     id: int,
     *     gym_id: int,
     *     name: string,
     *     can_access: bool,
     *     denial_reason: string|null,
     *     is_frozen: bool,
     *     payment_status: string|null,
     *     membership_end: string|null,
     *     has_consent: bool
     * } $summary
     */
    public function putMemberSummary(int $gymId, array $summary): void
    {
        $ttl = self::DEFAULT_TTL_SECONDS;

        Cache::put($this->buildMemberKey($gymId, $summary['id']), $summary, $ttl);
    }

    /**
     * @return array{
     *     id: int,
     *     gym_id: int,
     *     name: string,
     *     can_access: bool,
     *     denial_reason: string|null,
     *     is_frozen: bool,
     *     payment_status: string|null,
     *     membership_end: string|null,
     *     has_consent: bool
     * }
     */
    private function buildSummaryFromModel(GymMember $member): array
    {
        $canAccess = true;
        $denialReason = null;

        // Denial is delegated to the guard so this summary cannot fork from the
        // predicate that denies: MembershipGuard::resolveReason() walks
        // GymMember::canAccessGym()'s clauses in the same order, archive first.
        // Resolving the denial BEFORE the consent check mirrors the guard's own
        // precedence — archiving is a deliberate revocation and outranks the
        // incidental states an archived member often also happens to be in, so
        // "this person was archived" is what the summary reports. Clearing
        // can_access here is also what keeps the in-memory membership_end
        // re-check in getMemberAccessSummary() from overwriting denial_reason
        // with 'membership_expired' on a cache hit — it only rewrites while
        // can_access is still true.
        if (! $member->canAccessGym()) {
            $canAccess = false;
            $denialReason = resolve(MembershipGuard::class)->resolveReason($member);
        } elseif (! $member->hasActiveBiometricConsent()) {
            $canAccess = false;
            $denialReason = 'no_biometric_consent';
        }

        return [
            'id' => $member->id,
            'gym_id' => $member->gym_id,
            'name' => $member->name ?? 'Member',
            'can_access' => $canAccess,
            'denial_reason' => $denialReason,
            'is_frozen' => $member->isFrozen(),
            'payment_status' => $member->payment_status?->value,
            'membership_end' => $member->membership_end?->toISOString(),
            'has_consent' => $member->hasActiveBiometricConsent(),
        ];
    }

    /**
     * The cache key a member's access summary is stored under.
     *
     * Public so that callers which need to inspect or invalidate the entry —
     * chiefly tests asserting whether a read was served from cache — name the
     * same key the service writes, rather than duplicating the format and
     * silently drifting from it when the layout changes.
     */
    public function memberAccessSummaryKey(int $gymId, int|string $identifier): string
    {
        return $this->buildMemberKey($gymId, $identifier);
    }

    private function buildMemberKey(int $gymId, int|string $identifier): string
    {
        return 'biometric:gym:' . $gymId . ':member:' . $identifier;
    }
}
