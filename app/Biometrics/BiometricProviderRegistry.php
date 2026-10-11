<?php

declare(strict_types=1);

namespace App\Biometrics;

use App\Biometrics\Contracts\BiometricProvider;
use App\Enums\BiometricProvidersEnum;
use InvalidArgumentException;

class BiometricProviderRegistry
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function resolve(BiometricProvidersEnum|string $providerType, array $config = []): BiometricProvider
    {
        $typeKey = $providerType instanceof BiometricProvidersEnum ? $providerType->value : $providerType;

        /** @var class-string<BiometricProvider>|null $class */
        $class = config("biometrics.providers.{$typeKey}");

        throw_if($class === null, InvalidArgumentException::class, "Unsupported biometric provider: {$typeKey}");

        /** @var array<string, mixed> $systemConfig */
        $systemConfig = config("biometrics.providers_config.{$typeKey}", []);

        // Filter out null or empty string overrides so system credentials are not wiped
        $filteredConfig = array_filter($config, static fn ($value): bool => $value !== null && $value !== '');

        return new $class(array_merge($systemConfig, $filteredConfig));
    }

    public function isSupported(BiometricProvidersEnum|string $providerType): bool
    {
        $typeKey = $providerType instanceof BiometricProvidersEnum ? $providerType->value : $providerType;

        return config("biometrics.providers.{$typeKey}") !== null;
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
