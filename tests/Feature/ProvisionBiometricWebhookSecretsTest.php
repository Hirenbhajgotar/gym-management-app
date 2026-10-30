<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The provisioning command is the deployment cutover for the fail-closed webhook
 * posture. Its assertions are on state — the generated secret's shape and the
 * non-overwrite guarantee — rather than on substrings of the printed sentence,
 * so a change to the summary wording cannot make an unmet guarantee look green.
 */
class ProvisionBiometricWebhookSecretsTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
    }

    public function test_a_device_without_a_webhook_secret_is_provisioned_and_other_config_keys_survive(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'provider_config' => ['threshold' => 0.8, 'region' => 'eu-west-1'],
        ]);

        $this->artisan('biometrics:provision-webhook-secrets')
            ->expectsOutputToContain('1 provisioned')
            ->assertSuccessful();

        $device->refresh();

        $this->assertIsArray($device->provider_config);

        $secret = $device->provider_config['webhook_secret'] ?? null;

        $this->assertIsString($secret);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            $secret,
            'The webhook secret must be 32 random bytes hex-encoded (64 hex chars).'
        );

        $this->assertSame(0.8, $device->provider_config['threshold'] ?? null);
        $this->assertSame('eu-west-1', $device->provider_config['region'] ?? null);
    }

    public function test_a_device_that_already_has_a_secret_is_not_overwritten(): void
    {
        $device = BiometricDevice::factory()
            ->withWebhookSecret('existing-secret')
            ->create(['gym_id' => $this->gym->id]);

        $this->artisan('biometrics:provision-webhook-secrets')
            ->expectsOutputToContain('1 already had a secret')
            ->assertSuccessful();

        $device->refresh();

        $this->assertSame('existing-secret', $device->provider_config['webhook_secret'] ?? null);
    }

    public function test_a_device_with_null_provider_config_is_provisioned(): void
    {
        $device = BiometricDevice::factory()
            ->deviceNative()
            ->create(['gym_id' => $this->gym->id]);

        $this->artisan('biometrics:provision-webhook-secrets')->assertSuccessful();

        $device->refresh();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            (string) ($device->provider_config['webhook_secret'] ?? '')
        );
    }

    public function test_dry_run_does_not_write_anything(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'provider_config' => ['threshold' => 0.8],
        ]);

        $this->artisan('biometrics:provision-webhook-secrets', ['--dry-run' => true])
            ->expectsOutputToContain('would be provisioned')
            ->assertSuccessful();

        $device->refresh();

        $this->assertNull($device->provider_config['webhook_secret'] ?? null);
        $this->assertSame(0.8, $device->provider_config['threshold'] ?? null);
    }

    public function test_gym_option_scopes_the_run(): void
    {
        $otherGym = Gym::factory()->create();

        $device = BiometricDevice::factory()->create(['gym_id' => $this->gym->id]);
        $otherDevice = BiometricDevice::factory()->create(['gym_id' => $otherGym->id]);

        $this->artisan('biometrics:provision-webhook-secrets', ['--gym' => $this->gym->id])
            ->expectsOutputToContain('1 provisioned')
            ->assertSuccessful();

        $device->refresh();
        $otherDevice->refresh();

        $this->assertNotEmpty($device->provider_config['webhook_secret'] ?? null);
        $this->assertNull($otherDevice->provider_config['webhook_secret'] ?? null);
    }

    public function test_gym_scoped_dry_run_still_writes_nothing(): void
    {
        $device = BiometricDevice::factory()->create(['gym_id' => $this->gym->id]);

        $this->artisan('biometrics:provision-webhook-secrets', [
            '--gym' => $this->gym->id,
            '--dry-run' => true,
        ])->expectsOutputToContain('would be provisioned')
            ->assertSuccessful();

        $device->refresh();

        $this->assertNull($device->provider_config['webhook_secret'] ?? null);
    }

    public function test_an_all_configured_table_provisions_nothing(): void
    {
        BiometricDevice::factory()->withWebhookSecret('one')->create(['gym_id' => $this->gym->id]);
        BiometricDevice::factory()->withWebhookSecret('two')->create(['gym_id' => $this->gym->id]);

        $this->artisan('biometrics:provision-webhook-secrets')
            ->expectsOutputToContain('2 already had a secret')
            ->assertSuccessful();
    }

    public function test_an_empty_table_examines_nothing(): void
    {
        $this->artisan('biometrics:provision-webhook-secrets')
            ->expectsOutputToContain('0 device(s) examined, 0 provisioned, 0 already had a secret.')
            ->assertSuccessful();
    }

    public function test_a_non_string_webhook_secret_is_not_treated_as_configured(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'provider_config' => [
                'webhook_secret' => ['nested' => 'not-a-string'],
                'threshold' => 0.8,
            ],
        ]);

        $this->artisan('biometrics:provision-webhook-secrets')
            ->expectsOutputToContain('non-string webhook secret')
            ->assertFailed();

        $this->assertSame(
            ['nested' => 'not-a-string'],
            $device->refresh()->provider_config['webhook_secret'] ?? null
        );

        $this->artisan('biometrics:provision-webhook-secrets', ['--replace-unreadable' => true])
            ->assertSuccessful();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            (string) ($device->refresh()->provider_config['webhook_secret'] ?? '')
        );
    }

    public function test_reveal_prints_the_generated_secret_for_the_operator_to_install(): void
    {
        $device = BiometricDevice::factory()->create(['gym_id' => $this->gym->id]);

        $this->artisan('biometrics:provision-webhook-secrets', ['--reveal' => true])
            ->expectsOutputToContain("device {$device->id}")
            ->assertSuccessful();

        $device->refresh();
        $secret = (string) ($device->provider_config['webhook_secret'] ?? '');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);

        // The revealed value is the one now stored, so the operator installs the
        // secret the controller will actually verify against.
        $this->artisan('biometrics:provision-webhook-secrets', ['--reveal' => true])
            ->expectsOutputToContain($secret)
            ->assertSuccessful();
    }

    public function test_reveal_with_dry_run_warns_that_there_is_nothing_to_reveal(): void
    {
        BiometricDevice::factory()->create(['gym_id' => $this->gym->id]);

        $this->artisan('biometrics:provision-webhook-secrets', [
            '--dry-run' => true,
            '--reveal' => true,
        ])->expectsOutputToContain('Dry run generated no secrets')
            ->assertSuccessful();
    }

    public function test_invalid_gym_option_fails(): void
    {
        $this->artisan('biometrics:provision-webhook-secrets', ['--gym' => '-1'])
            ->expectsOutputToContain('The --gym option must be a positive integer.')
            ->assertFailed();

        $this->artisan('biometrics:provision-webhook-secrets', ['--gym' => 'invalid'])
            ->expectsOutputToContain('The --gym option must be a positive integer.')
            ->assertFailed();
    }

    public function test_an_unreadable_provider_config_is_skipped_and_reported(): void
    {
        $rawCiphertext = 'not-a-valid-encrypted-payload';

        $device = BiometricDevice::factory()->create(['gym_id' => $this->gym->id]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => $rawCiphertext]);

        $this->artisan('biometrics:provision-webhook-secrets')
            ->expectsOutputToContain('1 device(s) have an unreadable provider_config')
            ->assertFailed();

        $this->assertSame(
            $rawCiphertext,
            DB::table('biometric_devices')->where('id', $device->id)->value('provider_config')
        );
    }

    public function test_replace_unreadable_flag_explicitly_discards_the_ciphertext(): void
    {
        $rawCiphertext = 'not-a-valid-encrypted-payload';

        $device = BiometricDevice::factory()->create(['gym_id' => $this->gym->id]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => $rawCiphertext]);

        $this->artisan('biometrics:provision-webhook-secrets', [
            '--replace-unreadable' => true,
            '--reveal' => true,
        ])->assertSuccessful();

        $device->refresh();

        $this->assertFalse($device->providerConfigIsUnreadable());
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            (string) ($device->provider_config['webhook_secret'] ?? '')
        );
    }
}
