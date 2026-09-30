<?php

declare(strict_types=1);

namespace Tests\Unit\Biometric;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Providers\AzureFaceProvider;
use App\Biometrics\Providers\BioIDProvider;
use App\Biometrics\Providers\RekognitionProvider;
use App\Enums\BiometricProvidersEnum;
use ReflectionClass;
use Tests\TestCase;

class BiometricProviderRegistryTest extends TestCase
{
    protected BiometricProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new BiometricProviderRegistry();
    }

    public function test_it_resolves_rekognition_with_system_credentials(): void
    {
        config([
            'biometrics.providers_config.rekognition' => [
                'region' => 'eu-west-1',
                'access_key' => 'TEST_SYS_REKOGNITION_KEY',
                'secret_key' => 'TEST_SYS_REKOGNITION_SECRET',
                'similarity_threshold' => 85.0,
            ],
        ]);

        $provider = $this->registry->resolve(BiometricProvidersEnum::Rekognition);

        $this->assertInstanceOf(RekognitionProvider::class, $provider);

        $reflection = new ReflectionClass($provider);
        $configProp = $reflection->getProperty('config');
        $configProp->setAccessible(true);
        $config = $configProp->getValue($provider);
        $this->assertIsArray($config);

        $this->assertEquals('TEST_SYS_REKOGNITION_KEY', $config['access_key']);
        $this->assertEquals('TEST_SYS_REKOGNITION_SECRET', $config['secret_key']);
        $this->assertEquals('eu-west-1', $config['region']);
    }

    public function test_it_preserves_device_threshold_override_without_wiping_system_credentials(): void
    {
        config([
            'biometrics.providers_config.rekognition' => [
                'region' => 'us-east-1',
                'access_key' => 'TEST_SYS_KEY_OVERRIDE',
                'secret_key' => 'TEST_SYS_SECRET_OVERRIDE',
                'similarity_threshold' => 85.0,
            ],
        ]);

        // Device passes custom threshold and empty/null strings for credentials
        $deviceConfig = [
            'similarity_threshold' => 95.0,
            'access_key' => null,
            'secret_key' => '',
        ];

        $provider = $this->registry->resolve(BiometricProvidersEnum::Rekognition, $deviceConfig);

        $reflection = new ReflectionClass($provider);
        $configProp = $reflection->getProperty('config');
        $configProp->setAccessible(true);
        $config = $configProp->getValue($provider);
        $this->assertIsArray($config);

        $this->assertEquals('TEST_SYS_KEY_OVERRIDE', $config['access_key']);
        $this->assertEquals('TEST_SYS_SECRET_OVERRIDE', $config['secret_key']);
        $this->assertEquals(95.0, $config['similarity_threshold']);
    }

    public function test_it_resolves_azure_face_with_system_credentials(): void
    {
        config([
            'biometrics.providers_config.azure_face' => [
                'endpoint' => 'https://test-region.api.cognitive.microsoft.com',
                'subscription_key' => 'TEST_SYS_AZURE_KEY',
                'location' => 'westeurope',
                'confidence_threshold' => 80.0,
            ],
        ]);

        $provider = $this->registry->resolve(BiometricProvidersEnum::AzureFace);

        $this->assertInstanceOf(AzureFaceProvider::class, $provider);

        $reflection = new ReflectionClass($provider);
        $subKeyProp = $reflection->getProperty('subscriptionKey');
        $subKeyProp->setAccessible(true);
        $this->assertEquals('TEST_SYS_AZURE_KEY', $subKeyProp->getValue($provider));

        $endpointProp = $reflection->getProperty('endpoint');
        $endpointProp->setAccessible(true);
        $this->assertEquals('https://test-region.api.cognitive.microsoft.com', $endpointProp->getValue($provider));
    }

    public function test_it_resolves_bioid_with_system_credentials(): void
    {
        config([
            'biometrics.providers_config.bioid' => [
                'wsdl_url' => 'https://bws.bioid.com/sys-test',
                'app_identifier' => 'SYS_APP_IDENTIFIER',
                'api_key' => 'SYS_BIOID_SECRET',
                'service_id' => '42',
                'enable_liveness' => true,
            ],
        ]);

        $provider = $this->registry->resolve(BiometricProvidersEnum::BioID);

        $this->assertInstanceOf(BioIDProvider::class, $provider);

        $reflection = new ReflectionClass($provider);
        $configProp = $reflection->getProperty('config');
        $configProp->setAccessible(true);
        $config = $configProp->getValue($provider);
        $this->assertIsArray($config);

        $this->assertEquals('https://bws.bioid.com/sys-test', $config['wsdl_url']);
        $this->assertEquals('SYS_APP_IDENTIFIER', $config['app_identifier']);
        $this->assertEquals('SYS_BIOID_SECRET', $config['api_key']);
        $this->assertEquals('42', $config['service_id']);
    }
}
