<?php

declare(strict_types=1);

namespace App\Biometrics\ValueObjects;

/**
 * Outcome of fanning an enrolment out to sibling devices.
 *
 * The controller reports `success: true` for a *partial* fan-out and adds
 * `partial_success: true`, so these two accessors intentionally differ:
 * `isFullSuccess()` asks whether every sibling accepted the command, while
 * `totalFanoutFailed()` asks whether every sibling rejected it.
 */
final readonly class BiometricPropagationResult
{
    /**
     * @param  array<int, int>  $failedDeviceIds
     * @param  array<int, string>  $failedDeviceNames
     */
    public function __construct(
        public int $queuedCount,
        public int $alreadyProvisionedCount,
        public array $failedDeviceIds,
        public array $failedDeviceNames,
        public int $totalSiblings,
    ) {}

    /**
     * True when no sibling device rejected the fan-out.
     */
    public function isFullSuccess(): bool
    {
        return count($this->failedDeviceIds) === 0;
    }

    /**
     * True when at least one sibling device rejected the fan-out.
     */
    public function hasFailures(): bool
    {
        return count($this->failedDeviceIds) > 0;
    }

    /**
     * True when siblings existed and every one of them failed, meaning the enrolment was
     * confirmed locally but no sibling holds the credential.
     */
    public function totalFanoutFailed(): bool
    {
        return $this->totalSiblings > 0
            && count($this->failedDeviceIds) === $this->totalSiblings;
    }
}
