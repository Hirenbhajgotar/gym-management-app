<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class BiometricRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('biometric-api:127.0.0.1:iclock');
        RateLimiter::clear('biometric-api:127.0.0.1:biometric');
    }

    public function test_rate_limiter_keys_on_ip_and_blocks_sn_spoofing_bypass(): void
    {
        // Use a small limit in test to avoid 120s execution delay (S-5)
        config()->set('biometrics.device_throttle', 3);
        $limit = 3;

        // Exhaust the rate limit bucket for 127.0.0.1 with varying SN parameters
        for ($i = 0; $i < $limit; $i++) {
            $response = $this->get("/api/iclock/cdata?SN=device_{$i}");
            // Pre-auth should be a clean 401, not a 429 or a 500.
            $response->assertUnauthorized();
        }

        // The next request from the same IP with a new SN must be throttled with 429 Too Many Requests
        $throttledResponse = $this->get('/api/iclock/cdata?SN=device_bypass_attempt');
        $throttledResponse->assertStatus(429);
    }

    public function test_rate_limiter_allows_disabling_when_limit_is_zero(): void
    {
        config()->set('biometrics.device_throttle', 0);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->get("/api/iclock/cdata?SN=device_{$i}");
            $response->assertUnauthorized();
        }
    }

    public function test_rate_limiter_partitions_iclock_and_biometric_api_buckets(): void
    {
        config()->set('biometrics.device_throttle', 2);
        $limit = 2;

        // Exhaust the iclock bucket
        for ($i = 0; $i < $limit; $i++) {
            $response = $this->get("/api/iclock/cdata?SN=device_{$i}");
            $response->assertUnauthorized();
        }

        // iclock should now return 429
        $iclockThrottled = $this->get('/api/iclock/cdata?SN=device_exhausted');
        $iclockThrottled->assertStatus(429);

        // A request to the biometric API endpoint should NOT be throttled by the iclock exhaustion
        $biometricResponse = $this->postJson('/api/v1/biometric/check-in', []);
        $biometricResponse->assertUnauthorized();
    }

    public function test_rate_limiter_normalizes_ipv4_mapped_ipv6_to_ipv4(): void
    {
        $request = Request::create(
            '/api/iclock/cdata',
            'GET',
            server: ['REMOTE_ADDR' => '::ffff:192.0.2.1']
        );

        $limit = RateLimiter::limiter('biometric-api')($request);

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame('192.0.2.1:iclock', $limit->key);

        $hexMapped = Request::create(
            '/api/iclock/cdata',
            'GET',
            server: ['REMOTE_ADDR' => '::ffff:c000:201']
        );

        $hexLimit = RateLimiter::limiter('biometric-api')($hexMapped);

        $this->assertInstanceOf(Limit::class, $hexLimit);
        $this->assertSame('192.0.2.1:iclock', $hexLimit->key);
    }

    public function test_rate_limiter_keys_native_ipv6_by_64_prefix(): void
    {
        $request = Request::create(
            '/api/iclock/cdata',
            'GET',
            server: ['REMOTE_ADDR' => '2001:db8:1:2:3:4:5:6']
        );

        $limit = RateLimiter::limiter('biometric-api')($request);

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame('2001:db8:1:2:::iclock', $limit->key);
    }
}
