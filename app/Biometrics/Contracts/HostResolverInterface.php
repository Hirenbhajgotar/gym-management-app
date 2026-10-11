<?php

declare(strict_types=1);

namespace App\Biometrics\Contracts;

interface HostResolverInterface
{
    /**
     * Resolve a hostname to its IP address, or return null if unresolvable.
     */
    public function resolve(string $host): ?string;
}
