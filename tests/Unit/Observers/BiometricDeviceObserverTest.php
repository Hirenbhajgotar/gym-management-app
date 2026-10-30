<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Models\BiometricDevice;
use App\Observers\BiometricDeviceObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class BiometricDeviceObserverTest extends TestCase
{
    public function test_cache_invalidation_failure_is_best_effort(): void
    {
        $device = new BiometricDevice([
            'gym_id' => 7,
            'device_name' => 'Observer Test Device',
        ]);

        Cache::shouldReceive('forget')->andThrow(new RuntimeException('cache down'));
        Cache::shouldReceive('flush')->andReturnTrue();

        Log::spy();

        // The observer runs after commit. A cache outage must not turn a
        // committed device save into a reported failure.
        (new BiometricDeviceObserver())->created($device);

        Log::shouldHaveReceived('warning')
            ->atLeast()
            ->once();
    }
}
