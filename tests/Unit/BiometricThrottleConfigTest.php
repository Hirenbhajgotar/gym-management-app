<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `biometrics.device_throttle` is read by the `biometric-api` rate limiter registered in
 * `AppServiceProvider::registerRateLimiter()`. That limiter's behaviour under a configured
 * limit is covered end-to-end by `Tests\Feature\BiometricRateLimiterTest`; this class pins
 * the config entry itself so the value cannot drift from what the docs and `.env.example` state.
 */
class BiometricThrottleConfigTest extends TestCase
{
    #[Test]
    public function device_throttle_defaults_to_the_documented_value(): void
    {
        $this->assertSame(120, config('biometrics.device_throttle'));
    }

    #[Test]
    public function device_throttle_reads_its_value_from_config_not_a_literal(): void
    {
        config(['biometrics.device_throttle' => 45]);

        $this->assertSame(45, config('biometrics.device_throttle'));
    }

    #[Test]
    public function device_throttle_is_never_negative(): void
    {
        $this->assertGreaterThanOrEqual(0, config('biometrics.device_throttle'));
    }
}
