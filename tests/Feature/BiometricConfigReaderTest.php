<?php

declare(strict_types=1);

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class BiometricConfigReaderTest extends TestCase
{
    public function test_every_biometric_config_key_has_at_least_one_reader_in_codebase(): void
    {
        $config = config('biometrics');
        $this->assertIsArray($config);

        /** @var array<string, mixed> $config */
        $keys = $this->collectConfigKeys($config);

        $directories = [
            app_path(),
            base_path('routes'),
        ];

        foreach ($keys as $key) {
            $pattern = "biometrics.{$key}";
            $found = $this->searchForPattern($directories, $pattern, $key);

            $this->assertTrue(
                $found,
                "Biometric config key [{$pattern}] has no consumer/reader in app/ or routes/."
            );
        }
    }

    /**
     * @param  array<array-key, mixed>  $array
     * @return array<int, string>
     */
    private function collectConfigKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $keyStr = (string) $key;
            $fullKey = $prefix === '' ? $keyStr : "{$prefix}.{$keyStr}";

            // For providers and providers_config, the parent namespace is referenced dynamically (e.g. biometrics.providers.{$type})
            if ($fullKey === 'providers' || $fullKey === 'providers_config') {
                $keys[] = $fullKey;

                continue;
            }

            if (is_array($value) && ! array_is_list($value) && filled($value)) {
                $keys = array_merge($keys, $this->collectConfigKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }

        return $keys;
    }

    /**
     * @param  array<int, string>  $directories
     */
    private function searchForPattern(array $directories, string $fullPattern, string $shortKey): bool
    {
        foreach ($directories as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    if ($content === false) {
                        continue;
                    }

                    if (str_contains($content, $fullPattern) || str_contains($content, "'{$shortKey}'") || str_contains($content, "\"{$shortKey}\"")) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
