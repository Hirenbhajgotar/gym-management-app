<?php

declare(strict_types=1);

namespace App\Biometrics\ValueObjects;

/**
 * Parsed, canonical ADMS idempotency key.
 *
 * Two producers exist, and the format is a wire contract that outlives any single
 * caller, so it is parsed in exactly one place:
 *
 *   user_add:    user_add:{gymId}:{deviceId}:{memberId}[:{stage}]:{templateId}
 *   user_delete: user_delete:{deviceId}:{pin}
 *
 * The `user_add` form is emitted by {@see \App\Models\AdmsDeviceCommand::idempotencyKeyFor()}.
 * `stage` is present only for commands queued by the sibling propagator (`sync`) or by the
 * nightly enrollment reconciler (`reconcile`).
 *
 * Parsing fails closed. A key whose segment count or segment shape is not one of the canonical
 * forms yields null rather than a best-effort guess, because the consumer decides which
 * template a hardware acknowledgement belongs to: a wrong answer stamps or promotes the wrong
 * row, and a silently-dropped unknown segment would hide a producer change until it mis-routed
 * in production.
 */
final readonly class AdmsCommandKey
{
    /**
     * Lifecycle stage for a sibling-device fan-out `user_add`.
     */
    public const STAGE_SYNC = 'sync';

    /**
     * Lifecycle stage for a nightly-reconciler `user_add`.
     */
    public const STAGE_RECONCILE = 'reconcile';

    private function __construct(
        public string $commandType,
        public ?int $gymId,
        public ?int $deviceId,
        public ?int $memberId,
        public ?string $stage,
        public ?int $templateId,
        public ?string $pin,
    ) {}

    /**
     * Parse a canonical key, returning null when it does not match the known formats.
     */
    public static function tryParse(?string $key): ?self
    {
        if (blank($key)) {
            return null;
        }

        $parts = explode(':', $key);

        return match ($parts[0]) {
            'user_add' => self::parseUserAdd($parts),
            'user_delete' => self::parseUserDelete($parts),
            default => null,
        };
    }

    /**
     * True when this key was queued by the sibling propagator rather than by enrolment initiation.
     */
    public function isSiblingSync(): bool
    {
        return $this->stage === self::STAGE_SYNC;
    }

    /**
     * Build the canonical `user_add` key. Inverse of {@see self::tryParse()}.
     */
    public static function buildUserAdd(
        int $gymId,
        int $deviceId,
        int|string $memberId,
        ?int $templateId = null,
        ?string $stage = null,
    ): string {
        $parts = ["user_add:{$gymId}:{$deviceId}:{$memberId}"];

        if ($stage !== null && $stage !== '') {
            $parts[] = $stage;
        }

        if ($templateId !== null) {
            $parts[] = (string) $templateId;
        }

        return implode(':', $parts);
    }

    /**
     * Build the canonical `user_delete` key. Inverse of {@see self::tryParse()}.
     *
     * The command erases a whole PIN, so the key is PIN-scoped. A template id is accepted only
     * for reading legacy four-segment keys written before the key became PIN-scoped; no producer
     * emits one, because the UNIQUE index on `idempotency_key` is the dedupe mechanism and a
     * template-scoped key would let two templates sharing a PIN each insert a command.
     */
    public static function buildUserDelete(int $deviceId, string $pin): string
    {
        return "user_delete:{$deviceId}:{$pin}";
    }

    /**
     * Maximum number of hardware deliveries before a command is considered exhausted.
     *
     * An "attempt" means exactly one delivery to the terminal, so the device-answer
     * path and the lease-timeout path agree: only `markSent()` and the stale-lease
     * re-lease in {@see \App\Models\AdmsDeviceCommand::leaseNext()} increment the counter.
     */
    public static function maxAttempts(): int
    {
        return max(1, config()->integer('biometrics.adms.max_attempts', 3));
    }

    /**
     * @param  array<int, string>  $parts
     */
    private static function parseUserAdd(array $parts): ?self
    {
        // user_add:gymId:deviceId:memberId[:stage][:templateId]
        if (! isset($parts[1], $parts[2], $parts[3])) {
            return null;
        }

        $gymId = self::positiveInt($parts[1]);
        $deviceId = self::positiveInt($parts[2]);
        $memberId = self::positiveInt($parts[3]);

        if ($gymId === null || $deviceId === null || $memberId === null) {
            return null;
        }

        $tail = array_slice($parts, 4);

        if ($tail === []) {
            return new self('user_add', $gymId, $deviceId, $memberId, null, null, null);
        }

        $templateId = array_pop($tail);

        if (self::positiveInt($templateId) === null) {
            return null;
        }

        if ($tail === []) {
            return new self('user_add', $gymId, $deviceId, $memberId, null, (int) $templateId, null);
        }

        if (count($tail) === 1 && self::isStage($tail[0])) {
            return new self('user_add', $gymId, $deviceId, $memberId, $tail[0], (int) $templateId, null);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $parts
     */
    private static function parseUserDelete(array $parts): ?self
    {
        // user_delete:deviceId:pin
        if (! isset($parts[1], $parts[2])) {
            return null;
        }

        $deviceId = self::positiveInt($parts[1]);
        $pin = $parts[2];

        if ($deviceId === null || $pin === '') {
            return null;
        }

        // A fourth segment can only be the template id of a legacy key. A PIN containing a
        // colon is not representable in this format, so any extra segment is tolerated here
        // rather than rejected: rejecting it would make a durable legacy row unparseable.
        $templateId = isset($parts[3]) ? self::positiveInt($parts[3]) : null;

        return new self('user_delete', null, $deviceId, null, null, $templateId, $pin);
    }

    private static function isStage(string $value): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $value) === 1;
    }

    private static function positiveInt(string $value): ?int
    {
        if (! ctype_digit($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
