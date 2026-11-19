<?php

declare(strict_types=1);

namespace Tests\Concerns;

/**
 * Opt a test class out of booting Filament's panel route machinery.
 *
 * `FilamentServiceProvider::boot()` registers every panel/resource route on
 * every application boot. Suites that only exercise API endpoints never use
 * those routes but pay ~10s per test for them. Call `skipFilamentBoot()`
 * before `parent::setUp()` and `restoreFilamentBoot()` in `tearDown()` so
 * Filament-dependent tests running later in the same process are unaffected.
 */
trait SkipsFilamentBoot
{
    protected function skipFilamentBoot(): void
    {
        putenv('TESTS_SKIP_FILAMENT_PROVIDER=1');
        $_ENV['TESTS_SKIP_FILAMENT_PROVIDER'] = '1';
        $_SERVER['TESTS_SKIP_FILAMENT_PROVIDER'] = '1';
    }

    protected function restoreFilamentBoot(): void
    {
        putenv('TESTS_SKIP_FILAMENT_PROVIDER');
        unset($_ENV['TESTS_SKIP_FILAMENT_PROVIDER'], $_SERVER['TESTS_SKIP_FILAMENT_PROVIDER']);
    }
}
