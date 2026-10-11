<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Contracts\HostResolverInterface;
use App\Biometrics\Services\DoorControlService;
use ReflectionClass;
use Tests\TestCase;

class DoorControlSsrfTest extends TestCase
{
    public function test_deny_by_default_when_no_trusted_domains_or_subnets_configured(): void
    {
        config(['biometrics.trusted_domains' => []]);
        config(['biometrics.trusted_subnets' => []]);

        /** @var DoorControlService $service */
        $service = resolve(DoorControlService::class);

        // Reflection to test protected validateDoorControllerUrl
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('validateDoorControllerUrl');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, 'http://127.0.0.1/unlock'));
        $this->assertFalse($method->invoke($service, 'http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse($method->invoke($service, 'http://192.168.1.100:8080/unlock'));
        $this->assertFalse($method->invoke($service, 'https://example.com/unlock'));
    }

    public function test_trusted_domains_and_subnets_allow_matching_urls_only(): void
    {
        config(['biometrics.trusted_domains' => ['doors.mygym.com']]);
        config(['biometrics.trusted_subnets' => ['192.168.1.0/24']]);

        // Mock HostResolverInterface so doors.mygym.com resolves to a valid public IP
        $mockResolver = new class() implements HostResolverInterface
        {
            public function resolve(string $host): ?string
            {
                if (str_ends_with(strtolower($host), 'doors.mygym.com')) {
                    return '203.0.113.10'; // Public documentation IP
                }

                return null;
            }
        };

        $this->app->instance(HostResolverInterface::class, $mockResolver);

        /** @var DoorControlService $service */
        $service = resolve(DoorControlService::class);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('validateDoorControllerUrl');
        $method->setAccessible(true);

        // Allowed domain resolving to public IP
        $this->assertTrue($method->invoke($service, 'https://doors.mygym.com/api/door/1/unlock'));
        $this->assertTrue($method->invoke($service, 'https://sub.doors.mygym.com/api/unlock'));

        // Disallowed domain
        $this->assertFalse($method->invoke($service, 'https://attacker.com/api/unlock'));

        // Allowed subnet IP
        $this->assertTrue($method->invoke($service, 'http://192.168.1.50/unlock'));

        // Disallowed IP (RFC1918 outside configured subnet, loopback, or metadata)
        $this->assertFalse($method->invoke($service, 'http://10.0.0.1/unlock'));
        $this->assertFalse($method->invoke($service, 'http://127.0.0.1/unlock'));
        $this->assertFalse($method->invoke($service, 'http://169.254.169.254/unlock'));
    }

    public function test_unresolvable_host_fails_closed(): void
    {
        config(['biometrics.trusted_domains' => ['doors.mygym.com']]);
        config(['biometrics.trusted_subnets' => []]);

        // Resolver fails to resolve the host
        $mockResolver = new class() implements HostResolverInterface
        {
            public function resolve(string $host): ?string
            {
                return null;
            }
        };

        $service = new DoorControlService($mockResolver);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('validateDoorControllerUrl');
        $method->setAccessible(true);

        // Even though domain matches trusted_domains, unresolvable host must fail closed
        $this->assertFalse($method->invoke($service, 'https://doors.mygym.com/unlock'));
    }

    public function test_dns_rebinding_to_private_ip_is_blocked(): void
    {
        config(['biometrics.trusted_domains' => ['doors.mygym.com']]);
        config(['biometrics.trusted_subnets' => []]);

        // Resolver returns a loopback/private IP (DNS rebinding attempt)
        $mockResolver = new class() implements HostResolverInterface
        {
            public function resolve(string $host): string
            {
                return '127.0.0.1';
            }
        };

        $service = new DoorControlService($mockResolver);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('validateDoorControllerUrl');
        $method->setAccessible(true);

        // Blocked because resolved IP is loopback and not in trusted_subnets
        $this->assertFalse($method->invoke($service, 'https://doors.mygym.com/unlock'));
    }
}
