<?php

declare(strict_types=1);

namespace Tests\Unit\Biometric;

use App\Models\BiometricDevice;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Policy for salted, unversioned PBKDF2 verifiers.
 *
 * The digest carries no iteration count, so the model must neither label a
 * working 100,000-count hash as unrecognized nor auto-select the whole
 * unversioned fleet for rotation.
 */
class BiometricDeviceCommKeyVerifierPolicyTest extends TestCase
{
    #[Test]
    public function a_100k_salted_unversioned_hash_is_recognized_and_verifies(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $hash = hash_pbkdf2('sha256', '123456', $salt, BiometricDevice::MIN_PBKDF2_ITERATIONS);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = $salt;
        $device->adms_comm_key_hash = $hash;

        $this->assertTrue($device->hasRecognizedCommKeyVerifier());
        $this->assertTrue($device->commKeyVerifierIsUnversioned());
        $this->assertFalse($device->commKeyVerifierNeedsReprovision());
        $this->assertTrue($device->verifyCommKeyWithoutLegacyFallback('123456'));
        $this->assertFalse($device->verifyCommKeyWithoutLegacyFallback('654321'));
    }

    #[Test]
    public function a_50k_salted_unversioned_hash_is_not_auto_selected_because_the_count_cannot_be_detected(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $hash = hash_pbkdf2('sha256', '123456', $salt, 50000);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = $salt;
        $device->adms_comm_key_hash = $hash;

        // Shape-wise it is indistinguishable from the working 100k hash.
        $this->assertTrue($device->hasRecognizedCommKeyVerifier());
        $this->assertTrue($device->commKeyVerifierIsUnversioned());

        // Chosen policy: never auto-reprovision a salted 64-hex unversioned
        // hash, because doing so would rotate the valid 100k fleet too.
        $this->assertFalse($device->commKeyVerifierNeedsReprovision());

        // Under the default 100,000 count it does not authenticate; the policy
        // accepts this dead-row case rather than breaking working devices.
        $this->assertFalse($device->verifyCommKeyWithoutLegacyFallback('123456'));

        // The same fixture does authenticate when the configured count is
        // 50,000, proving the count is not derivable from the hash.
        config(['biometrics.adms.pbkdf2_iterations' => 50000]);

        $this->assertTrue($device->verifyCommKeyWithoutLegacyFallback('123456'));
    }

    #[Test]
    public function a_salted_non_hex_hash_is_not_recognized_and_needs_reprovision(): void
    {
        $device = new BiometricDevice();
        $device->adms_comm_key_salt = 'legacy-salt';
        $device->adms_comm_key_hash = 'garbage';

        $this->assertFalse($device->hasRecognizedCommKeyVerifier());
        $this->assertFalse($device->commKeyVerifierIsUnversioned());
        $this->assertTrue($device->commKeyVerifierNeedsReprovision());
    }

    #[Test]
    public function a_versioned_out_of_range_hash_is_not_recognized_and_needs_reprovision(): void
    {
        config(['biometrics.adms.pbkdf2_min_iterations' => BiometricDevice::MIN_PBKDF2_ITERATIONS]);

        $device = new BiometricDevice();
        $device->adms_comm_key_salt = 'legacy-salt';
        $device->adms_comm_key_hash = BiometricDevice::PBKDF2_HASH_PREFIX . ':50000:' . str_repeat('a', 64);

        $this->assertFalse($device->hasRecognizedCommKeyVerifier());
        $this->assertFalse($device->commKeyVerifierIsUnversioned());
        $this->assertTrue($device->commKeyVerifierNeedsReprovision());
    }

    #[Test]
    public function an_unsalted_sha256_hash_is_recognized_but_needs_reprovision(): void
    {
        $device = new BiometricDevice();
        $device->adms_comm_key_salt = null;
        $device->adms_comm_key_hash = hash('sha256', '123456');

        $this->assertTrue($device->hasRecognizedCommKeyVerifier());
        $this->assertFalse($device->commKeyVerifierIsUnversioned());
        $this->assertTrue($device->commKeyVerifierNeedsReprovision());
    }

    #[Test]
    public function a_versioned_in_range_hash_is_not_unversioned(): void
    {
        $device = new BiometricDevice();
        $device->adms_comm_key_salt = 'legacy-salt';
        $device->adms_comm_key_hash = BiometricDevice::PBKDF2_HASH_PREFIX . ':100000:' . str_repeat('a', 64);

        $this->assertTrue($device->hasRecognizedCommKeyVerifier());
        $this->assertFalse($device->commKeyVerifierIsUnversioned());
        $this->assertFalse($device->commKeyVerifierNeedsReprovision());
    }
}
