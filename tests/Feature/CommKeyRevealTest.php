<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\LockProvider;
use App\Biometrics\Services\CommKeyReveal;
use App\Biometrics\Services\DeviceTokenReveal;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CommKeyRevealTest extends TestCase
{
    public function test_reveal_value_is_encrypted_and_consumed_once(): void
    {
        CommKeyReveal::put(42, '123456');

        $cached = Cache::get(CommKeyReveal::cacheKey(42));

        $this->assertIsString($cached);
        $this->assertStringNotContainsString('123456', $cached);

        $this->assertSame('123456', CommKeyReveal::pull(42));
        $this->assertNull(CommKeyReveal::pull(42));
    }

    public function test_cache_write_failure_is_reported_without_throwing(): void
    {
        $store = Mockery::mock(Store::class);
        Cache::shouldReceive('getStore')->once()->andReturn($store);
        Cache::shouldReceive('forget')->times(3)->andReturnTrue();
        Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('cache down'));
        Cache::shouldReceive('flush')->andReturnTrue();

        $this->assertFalse(CommKeyReveal::put(7, '123456'));
    }

    public function test_cache_read_failure_returns_null_without_throwing(): void
    {
        $store = Mockery::mock(Store::class);
        Cache::shouldReceive('getStore')->once()->andReturn($store);
        Cache::shouldReceive('pull')->once()->andThrow(new RuntimeException('cache down'));
        Cache::shouldReceive('flush')->andReturnTrue();

        $this->assertNull(CommKeyReveal::pull(7));
    }

    public function test_device_token_reveal_is_encrypted_and_consumed_once(): void
    {
        $this->assertTrue(DeviceTokenReveal::put(9, 'token-value'));

        $cached = Cache::get(DeviceTokenReveal::cacheKey(9));
        $this->assertIsString($cached);
        $this->assertStringNotContainsString('token-value', $cached);

        $this->assertSame('token-value', DeviceTokenReveal::pull(9));
        $this->assertNull(DeviceTokenReveal::pull(9));
    }

    public function test_cache_store_inspection_failure_fails_closed_instead_of_reading_unlocked(): void
    {
        Cache::shouldReceive('getStore')->once()->andThrow(new RuntimeException('cache manager failure'));
        Cache::shouldReceive('pull')->never();
        Cache::shouldReceive('flush')->andReturnTrue();

        $this->assertNull(CommKeyReveal::pull(29));
    }

    public function test_lock_timeout_fails_closed_instead_of_reading_unlocked(): void
    {
        CommKeyReveal::put(23, '123456');

        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()->andThrow(new LockTimeoutException('timeout'));

        $store = Mockery::mock(LockProvider::class);

        Cache::shouldReceive('getStore')->once()->andReturn($store);
        Cache::shouldReceive('lock')->once()->andReturn($lock);
        Cache::shouldReceive('pull')->never();
        Cache::shouldReceive('flush')->andReturnTrue();

        $this->assertNull(CommKeyReveal::pull(23));
    }

    public function test_put_invalidates_a_previous_delivery_copy(): void
    {
        CommKeyReveal::put(11, '111111');

        $first = CommKeyReveal::claimForDisplay(11);
        $this->assertSame('111111', $first['secret']);
        $this->assertTrue($first['claimed']);

        // A re-render reads the delivery copy.
        $this->assertSame('111111', CommKeyReveal::claimForDisplay(11)['secret']);

        CommKeyReveal::put(11, '222222');

        $second = CommKeyReveal::claimForDisplay(11);
        $this->assertSame('222222', $second['secret']);

        $this->assertSame('222222', CommKeyReveal::claimForDisplay(11)['secret']);
    }
}
