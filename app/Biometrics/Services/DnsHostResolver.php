<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\Contracts\HostResolverInterface;

class DnsHostResolver implements HostResolverInterface
{
    public function resolve(string $host): ?string
    {
        $ip = gethostbyname($host);

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }
}
