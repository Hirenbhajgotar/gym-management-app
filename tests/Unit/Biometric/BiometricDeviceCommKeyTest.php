<?php

declare(strict_types=1);

namespace Tests\Unit\Biometric;

use App\Enums\BiometricProvidersEnum;
use App\Models\BiometricDevice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiometricDeviceCommKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['biometrics.adms.pbkdf2_iterations' => 1000]);
    }

    #[Test]
    public function generated_comm_key_can_be_hashed_and_verified_successfully(): void
    {
        $plainKey = BiometricDevice::generateCommKey();
        $salt = BiometricDevice::generateCommKeySalt();
        $hash = BiometricDevice::hashCommKey($plainKey, $salt);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = $salt;
        $device->adms_comm_key_hash = $hash;

        $this->assertStringStartsWith(BiometricDevice::PBKDF2_HASH_PREFIX . ':1000:', $hash);
        $this->assertNotSame($plainKey, $hash);
        $this->assertTrue($device->verifyCommKey($plainKey));
        $this->assertFalse($device->verifyCommKey('000000'));
        $this->assertFalse($device->verifyCommKey('wrong1'));
    }

    #[Test]
    public function verify_comm_key_tolerates_whitespace_around_plain_key(): void
    {
        $plainKey = '123456';
        $salt = BiometricDevice::generateCommKeySalt();
        $hash = BiometricDevice::hashCommKey($plainKey, $salt);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = $salt;
        $device->adms_comm_key_hash = $hash;

        $this->assertTrue($device->verifyCommKey(' 123456 '));
        $this->assertTrue($device->verifyCommKey(' 123 456 '));
        $this->assertTrue($device->verifyCommKey("\t123\n456"));
        $this->assertFalse($device->verifyCommKey(' 654321 '));
        $this->assertFalse($device->verifyCommKey(''));
        $this->assertFalse($device->verifyCommKey(" \t\n"));
    }

    #[Test]
    public function pbkdf2_hash_created_from_a_whitespace_bearing_key_self_heals_to_the_sanitized_form(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $hash = BiometricDevice::hashCommKey(' 123 456 ', $salt);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = $salt;
        $device->adms_comm_key_hash = $hash;

        // The pre-change create/edit hooks hashed the raw form; the raw fallback
        // keeps that device authenticating after deploy and immediately re-hashes
        // the sanitized form so the fallback is not needed on the next request.
        $this->assertTrue($device->verifyCommKey(' 123 456 '));
        $this->assertTrue($device->verifyCommKey('123456'));
        $this->assertStringStartsWith(BiometricDevice::PBKDF2_HASH_PREFIX, (string) $device->adms_comm_key_hash);
    }

    #[Test]
    public function sanitized_presentation_of_a_raw_only_verifier_is_rejected_until_rotation(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $hash = BiometricDevice::hashCommKey(' 123 456 ', $salt);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = $salt;
        $device->adms_comm_key_hash = $hash;

        // A keypad can only present the sanitized form. A raw-only verifier
        // cannot heal from that form without the original plaintext; the ADR
        // documents this and points the operator at rotation.
        $this->assertFalse($device->verifyCommKey('123456'));
        $this->assertTrue($device->verifyCommKey(' 123 456 '));
    }

    #[Test]
    public function legacy_raw_fallback_can_be_disabled_after_the_rotation_window(): void
    {
        config(['biometrics.adms.allow_legacy_unsanitized_comm_key' => false]);

        $salt = BiometricDevice::generateCommKeySalt();
        $hash = BiometricDevice::hashCommKey(' 123 456 ', $salt);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = $salt;
        $device->adms_comm_key_hash = $hash;

        $this->assertFalse($device->verifyCommKey(' 123 456 '));
        $this->assertFalse($device->verifyCommKey('123456'));
    }

    #[Test]
    public function legacy_unsalted_sha256_hash_created_from_a_whitespace_bearing_key_self_heals(): void
    {
        $device = new BiometricDevice();
        $device->adms_comm_key_hash = hash('sha256', ' 123 456 ');
        $device->adms_comm_key_salt = null;

        $this->assertTrue($device->verifyCommKey(' 123 456 '));
        $this->assertTrue($device->verifyCommKey('123456'));
        $this->assertFalse($device->verifyCommKey('654321'));
        $this->assertNotNull($device->adms_comm_key_salt);
    }

    #[Test]
    public function provider_config_plaintext_key_with_whitespace_verifies_sanitized_and_exact_forms(): void
    {
        $device = new BiometricDevice();
        $device->adms_comm_key_hash = null;
        $device->adms_comm_key_salt = null;
        $device->provider_config = ['pushcommkey' => ' 123 456 ', 'other_setting' => 'safe'];

        $this->assertTrue($device->verifyCommKey('123456'));
        $this->assertTrue($device->verifyCommKey(' 123 456 '));
        $this->assertFalse($device->verifyCommKey('654321'));
    }

    #[Test]
    public function provider_config_comm_key_alias_is_also_supported(): void
    {
        $device = new BiometricDevice();
        $device->provider_config = ['comm_key' => ' ABC 123 '];

        $this->assertTrue($device->verifyCommKey('ABC123'));
        $this->assertTrue($device->hasCommKeyConfigured());
    }

    #[Test]
    public function pbkdf2_hash_is_self_describing_and_survives_an_iteration_config_change(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $hash = BiometricDevice::hashCommKey('123456', $salt);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = $salt;
        $device->adms_comm_key_hash = $hash;

        config(['biometrics.adms.pbkdf2_iterations' => 2000]);

        $this->assertTrue($device->verifyCommKey('123456'));
    }

    #[Test]
    public function legacy_unversioned_pbkdf2_hash_survives_a_config_iteration_change(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $legacyHash = hash_pbkdf2('sha256', '123456', $salt, 100000);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = $salt;
        $device->adms_comm_key_hash = $legacyHash;

        config(['biometrics.adms.pbkdf2_iterations' => 1000]);

        $this->assertTrue($device->verifyCommKey('123456'));
    }

    #[Test]
    public function hash_and_salt_are_hidden_from_serialization(): void
    {
        $device = new BiometricDevice();
        $device->adms_comm_key_hash = 'hash-value';
        $device->adms_comm_key_salt = 'salt-value';

        $serialized = $device->toArray();

        $this->assertArrayNotHasKey('adms_comm_key_hash', $serialized);
        $this->assertArrayNotHasKey('adms_comm_key_salt', $serialized);
    }

    #[Test]
    public function has_comm_key_configured_recognizes_hash_and_provider_config(): void
    {
        $withHash = new BiometricDevice();
        $withHash->adms_comm_key_hash = 'hash-value';
        $this->assertTrue($withHash->hasCommKeyConfigured());

        $withLegacyKey = new BiometricDevice();
        $withLegacyKey->provider_config = ['pushcommkey' => '123456'];
        $this->assertTrue($withLegacyKey->hasCommKeyConfigured());

        $withoutKey = new BiometricDevice();
        $withoutKey->provider_config = [];
        $this->assertFalse($withoutKey->hasCommKeyConfigured());
    }

    #[Test]
    public function cloud_provider_comm_key_is_a_provider_secret_not_an_adms_key(): void
    {
        $device = new BiometricDevice();
        $device->provider_type = BiometricProvidersEnum::Custom;
        $device->provider_config = ['comm_key' => 'cloud-provider-key'];

        $this->assertFalse($device->hasCommKeyConfigured());
        $this->assertNull($device->admsPlaintextCommKey());
        $this->assertFalse($device->verifyCommKey('cloud-provider-key'));
        $this->assertFalse($device->mayDeleteCommKeyAlias());
    }

    #[Test]
    public function null_provider_type_is_treated_as_device_native_for_verification_and_cleanup(): void
    {
        $device = new BiometricDevice();
        $device->provider_type = null;
        $device->provider_config = ['comm_key' => ' dual mode '];

        $this->assertTrue($device->hasCommKeyConfigured());
        $this->assertTrue($device->verifyCommKey('dualmode'));
        $this->assertTrue($device->mayDeleteCommKeyAlias());
    }

    #[Test]
    public function empty_primary_alias_falls_through_to_a_filled_comm_key_alias(): void
    {
        $device = new BiometricDevice();
        $device->provider_config = ['pushcommkey' => '   ', 'comm_key' => 'abc123'];

        $this->assertSame('abc123', $device->admsPlaintextCommKey());
        $this->assertFalse($device->hasAmbiguousCommKeyAliases());
        $this->assertTrue($device->verifyCommKey('abc123'));
    }

    #[Test]
    public function conflicting_aliases_are_flagged_and_pushcommkey_wins(): void
    {
        $device = new BiometricDevice();
        $device->provider_type = BiometricProvidersEnum::DeviceNative;
        $device->provider_config = ['pushcommkey' => '111111', 'comm_key' => '222222'];

        $this->assertTrue($device->hasAmbiguousCommKeyAliases());
        $this->assertSame('111111', $device->admsPlaintextCommKey());
        $this->assertTrue($device->verifyCommKey('111111'));
        $this->assertFalse($device->verifyCommKey('222222'));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function newKeyPolicyCases(): array
    {
        return [
            'one character' => ['1', false],
            'four characters' => ['1234', false],
            'five characters' => ['12345', false],
            'six numeric characters' => ['123456', true],
            'six alphanumeric characters' => ['ABC123', true],
            'formatted six digits' => [' 123 456 ', true],
            'seven characters' => ['1234567', false],
            'leading zero' => ['012345', false],
            'punctuation' => ['123-56', false],
            'empty' => ['', false],
            'whitespace only' => ['   ', false],
        ];
    }

    #[Test]
    #[DataProvider('newKeyPolicyCases')]
    public function new_key_policy_requires_exactly_six_alphanumeric_characters(string $key, bool $expected): void
    {
        $this->assertSame($expected, BiometricDevice::isValidCommKey($key));
    }

    #[Test]
    public function stored_pbkdf2_iterations_above_the_safe_maximum_are_rejected(): void
    {
        $device = new BiometricDevice();
        $device->adms_comm_key_salt = 'salt';
        $device->adms_comm_key_hash = BiometricDevice::PBKDF2_HASH_PREFIX . ':' . (BiometricDevice::MAX_PBKDF2_ITERATIONS + 1) . ':' . str_repeat('a', 64);

        $this->assertFalse($device->verifyCommKey('123456'));
    }

    #[Test]
    public function stored_pbkdf2_iterations_below_the_floor_are_rejected(): void
    {
        $device = new BiometricDevice();
        $device->adms_comm_key_salt = 'salt';
        $device->adms_comm_key_hash = BiometricDevice::PBKDF2_HASH_PREFIX . ':0:' . str_repeat('a', 64);

        $this->assertFalse($device->verifyCommKey('123456'));
    }

    #[Test]
    public function uppercase_hex_stored_hash_is_compared_case_insensitively(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $hash = BiometricDevice::hashCommKey('123456', $salt);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = $salt;
        $device->adms_comm_key_hash = strtoupper($hash);

        $this->assertTrue($device->verifyCommKey('123456'));
    }

    #[Test]
    public function hash_comm_key_clamps_explicit_iteration_counts(): void
    {
        $this->assertSame(BiometricDevice::MAX_PBKDF2_ITERATIONS, BiometricDevice::clampPbkdf2Iterations(BiometricDevice::MAX_PBKDF2_ITERATIONS + 1));
        $this->assertSame(1, BiometricDevice::clampPbkdf2Iterations(0));

        $tooLow = BiometricDevice::hashCommKey('123456', 'salt', 0);
        $this->assertStringStartsWith(BiometricDevice::PBKDF2_HASH_PREFIX . ':1:', $tooLow);
    }

    #[Test]
    public function hash_and_salt_are_not_mass_assignable(): void
    {
        $device = new BiometricDevice();
        $device->fill([
            'adms_comm_key_hash' => 'attacker-controlled',
            'adms_comm_key_salt' => 'attacker-controlled',
        ]);

        $this->assertNull($device->adms_comm_key_hash);
        $this->assertNull($device->adms_comm_key_salt);
    }

    #[Test]
    public function provider_config_is_hidden_from_serialization(): void
    {
        $device = new BiometricDevice();
        $device->provider_config = ['comm_key' => 'legacy-secret'];

        $this->assertArrayNotHasKey('provider_config', $device->toArray());
    }

    #[Test]
    public function corrupt_provider_config_reads_as_null_and_is_reported_unreadable(): void
    {
        $device = new BiometricDevice();
        $device->setRawAttributes(['provider_config' => 'not-a-valid-encrypted-payload'], true);

        $this->assertNull($device->provider_config);
        $this->assertTrue($device->providerConfigIsUnreadable());
        $this->assertFalse($device->hasCommKeyConfigured());
    }

    #[Test]
    public function production_pbkdf2_floor_is_enforced_independently_of_app_env(): void
    {
        config(['biometrics.adms.pbkdf2_min_iterations' => BiometricDevice::MIN_PBKDF2_ITERATIONS]);

        $this->assertSame(
            BiometricDevice::MIN_PBKDF2_ITERATIONS,
            BiometricDevice::clampPbkdf2Iterations(1)
        );
    }

    #[Test]
    public function testing_only_pbkdf2_floor_override_is_ignored_outside_the_testing_environment(): void
    {
        config(['biometrics.adms.pbkdf2_min_iterations' => 1]);

        $originalEnvironment = app()->get('env');
        app()->instance('env', 'production');

        try {
            $this->assertSame(
                BiometricDevice::MIN_PBKDF2_ITERATIONS,
                BiometricDevice::clampPbkdf2Iterations(1)
            );
        } finally {
            app()->instance('env', $originalEnvironment);
        }
    }

    #[Test]
    public function reprovision_detection_covers_unsalted_unsafe_and_corrupt_verifiers(): void
    {
        config(['biometrics.adms.pbkdf2_min_iterations' => BiometricDevice::MIN_PBKDF2_ITERATIONS]);

        $unsalted = new BiometricDevice();
        $unsalted->adms_comm_key_hash = hash('sha256', '123456');
        $unsalted->adms_comm_key_salt = null;
        $this->assertTrue($unsalted->commKeyVerifierNeedsReprovision());

        $whitespace = new BiometricDevice();
        $whitespace->adms_comm_key_hash = '   ';
        $whitespace->adms_comm_key_salt = null;
        $this->assertTrue($whitespace->commKeyVerifierNeedsReprovision());

        $unversioned = new BiometricDevice();
        $unversioned->adms_comm_key_salt = 'salt';
        $unversioned->adms_comm_key_hash = hash_pbkdf2('sha256', '123456', 'salt', 1000);
        $this->assertFalse($unversioned->commKeyVerifierNeedsReprovision());
        $this->assertTrue($unversioned->hasRecognizedCommKeyVerifier());
        $this->assertTrue($unversioned->commKeyVerifierIsUnversioned());

        $unsafe = new BiometricDevice();
        $unsafe->adms_comm_key_salt = 'salt';
        $unsafe->adms_comm_key_hash = BiometricDevice::PBKDF2_HASH_PREFIX . ':50000:' . str_repeat('a', 64);
        $this->assertTrue($unsafe->commKeyVerifierNeedsReprovision());

        $corrupt = new BiometricDevice();
        $corrupt->adms_comm_key_salt = 'salt';
        $corrupt->adms_comm_key_hash = 'garbage';
        $this->assertTrue($corrupt->commKeyVerifierNeedsReprovision());

        $safe = new BiometricDevice();
        $safe->adms_comm_key_salt = 'salt';
        $safe->adms_comm_key_hash = BiometricDevice::PBKDF2_HASH_PREFIX . ':100000:' . str_repeat('a', 64);
        $this->assertFalse($safe->commKeyVerifierNeedsReprovision());
    }
}
