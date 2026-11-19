<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * A test base for logic that needs `config()` but no Laravel application,
 * database, HTTP kernel or events.
 *
 * `Tests\TestCase::setUp()` boots the full application (79 service providers,
 * package/Filament discovery) for every single test. Pure serializer/enum/helper
 * tests pay that cost without using any of it. This base binds only a config
 * repository into a standalone container so `config()` keeps working while the
 * test runs in milliseconds.
 *
 * The only services provided are a config repository and a throwaway encrypter
 * (for Eloquent casts such as ProviderConfigCast). Tests here must not touch the
 * database, factories, HTTP routes, events, caches or application-only helpers
 * such as `app()->environment()`, `now()` and `fake()`. Use `Tests\TestCase`
 * for those — an in-memory model that reads a relation or a config-driven floor
 * will fail fast here rather than silently passing with the wrong state.
 */
abstract class LightweightTestCase extends BaseTestCase
{
    private ?Container $previousContainer = null;

    /** @var mixed The facade application that was active before this test. */
    private mixed $previousFacadeApplication = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();

        $container = new Container();
        $container->instance('config', new Repository([
            'biometrics' => [
                'device_throttle' => 120,
                'adms' => [
                    'user_table' => 'USERINFO',
                    'pbkdf2_iterations' => 100000,
                    'pbkdf2_min_iterations' => 1,
                ],
            ],
        ]));
        // Eloquent casts such as ProviderConfigCast encrypt their backing value.
        // A throwaway key is enough: nothing here is persisted or read across tests.
        $container->instance('encrypter', new Encrypter(
            Encrypter::generateKey('AES-256-CBC'),
            'AES-256-CBC'
        ));

        Container::setInstance($container);
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);

        $this->previousContainer = null;
        $this->previousFacadeApplication = null;

        parent::tearDown();
    }
}
