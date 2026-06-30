<?php

declare(strict_types=1);

namespace App\Biometrics;

use App\Biometrics\Contracts\BiometricProvider;
use InvalidArgumentException;

class BiometricProviderRegistry
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function resolve(string $providerType, array $config = []): BiometricProvider
    {
        /** @var class-string<BiometricProvider>|null $class */
        $class = config("biometrics.providers.{$providerType}");

        throw_if($class === null, InvalidArgumentException::class, "Unsupported biometric provider: {$providerType}");

        return new $class($config);
    }

    public function isSupported(string $providerType): bool
    {
        return config("biometrics.providers.{$providerType}") !== null;
    }

    /**
     * @return list<string>
     */
    public function getSupportedProviders(): array
    {
        /** @var array<string, class-string<BiometricProvider>>|null $providers */
        $providers = config('biometrics.providers');

        return array_keys($providers ?? []);
    }
}
