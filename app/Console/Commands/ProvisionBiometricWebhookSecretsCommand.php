<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\Services\WebhookSecretRotationService;
use App\Models\BiometricDevice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionBiometricWebhookSecretsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'biometrics:provision-webhook-secrets
                            {--gym= : Only provision devices for this gym id}
                            {--dry-run : Report what would change without writing}
                            {--replace-unreadable : Replace a provider_config ciphertext that cannot be decrypted}
                            {--reveal : Print each device\'s webhook secret so it can be installed on the terminal}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision a webhook signing secret for every biometric device missing one';

    /**
     * Backfill the deployment cutover for the fail-closed webhook posture.
     *
     * The webhook controller refuses any device that has no webhook_secret, so
     * every pre-existing secret-less device must be provisioned in the same
     * release or it silently stops delivering check-in and door events. This
     * command is idempotent: a device that already holds a non-empty secret is
     * never overwritten, so it is safe to re-run and safe to schedule.
     *
     * Provisioning alone does not restore delivery — the terminal still has to
     * be told the secret, or it moves from "no secret configured" to "signature
     * invalid" and the panel cannot tell "not yet re-provisioned" from
     * "misconfigured". `--reveal` is the cutover step: it prints each device id
     * and its secret for the operator to install. Secrets are deliberately not
     * printed without the flag, so a scheduled backfill does not leak them into
     * a scheduler log.
     *
     * provider_config is stored encrypted, so candidates cannot be selected
     * with a JSON path; devices are streamed and filtered in PHP instead.
     */
    public function handle(): int
    {
        $gymId = $this->option('gym');
        if ($gymId !== null) {
            $gymId = filter_var($gymId, FILTER_VALIDATE_INT);
            if ($gymId === false || $gymId <= 0) {
                $this->error('The --gym option must be a positive integer.');

                return self::FAILURE;
            }
        }

        $dryRun = $this->option('dry-run');
        $reveal = $this->option('reveal');
        $replaceUnreadable = $this->option('replace-unreadable');

        $examinedCount = 0;
        $provisionedCount = 0;
        $alreadyConfiguredCount = 0;
        $unreadableCount = 0;
        $lockFailureCount = 0;

        /** @var array<int, array{name: string, secret: string, provisioned: bool}> $revealed */
        $revealed = [];

        BiometricDevice::query()
            ->when($gymId, fn (Builder $q): Builder => $q->where('gym_id', $gymId))
            ->chunkById(100, function ($devices) use ($dryRun, $reveal, $replaceUnreadable, &$examinedCount, &$provisionedCount, &$alreadyConfiguredCount, &$unreadableCount, &$lockFailureCount, &$revealed): void {
                foreach ($devices as $device) {
                    $examinedCount++;

                    // The command and the edit/rotation paths all rewrite the
                    // whole encrypted config. Serialize on the same per-device
                    // lock or one writer silently overwrites the other.
                    try {
                        $lock = Cache::lock(
                            WebhookSecretRotationService::providerConfigLockKey(self::deviceKey($device)),
                            WebhookSecretRotationService::LOCK_TTL_SECONDS
                        );

                        $acquired = $lock->get();
                    } catch (Throwable $exception) {
                        Log::warning('Biometric webhook secret provisioning could not create a provider-config lock', [
                            'device_id' => $device->getKey(),
                            'exception' => $exception::class,
                        ]);

                        $lock = null;
                        $acquired = false;
                    }

                    if (! $acquired || $lock === null) {
                        $lockFailureCount++;

                        $this->warn(sprintf(
                            'Device [ID: %d, SN: %s] is being changed by another process; skipped.',
                            $device->id,
                            $device->device_serial ?? 'n/a'
                        ));

                        continue;
                    }

                    try {
                        // Another writer may have changed the config since this
                        // chunk was selected; re-check every condition against
                        // the row read under the lock.
                        $device->refresh();

                        if ($device->providerConfigIsUnreadable()) {
                            if (! $replaceUnreadable) {
                                // The ciphertext may be recoverable with an old
                                // APP_KEY or a backup. Never re-encrypt an empty
                                // array over it as a side effect of provisioning.
                                $unreadableCount++;

                                continue;
                            }
                        }

                        $config = is_array($device->provider_config) ? $device->provider_config : [];
                        $existingSecret = $config['webhook_secret'] ?? null;
                        $hasUsableSecret = is_string($existingSecret) && $existingSecret !== '';
                        $hasMalformedSecret = $existingSecret !== null
                            && $existingSecret !== ''
                            && ! $hasUsableSecret;

                        if ($hasMalformedSecret && ! $replaceUnreadable) {
                            // A malformed (array/numeric) secret is not usable for
                            // HMAC verification and must not be silently treated as
                            // configured. Only an explicit replacement discards it.
                            $unreadableCount++;
                            $this->warn(sprintf(
                                'Device [ID: %d, SN: %s] has a non-string webhook secret; skipped. Re-run with --replace-unreadable to replace it.',
                                $device->id,
                                $device->device_serial ?? 'n/a'
                            ));

                            continue;
                        }

                        if ($hasUsableSecret) {
                            $alreadyConfiguredCount++;

                            if ($reveal) {
                                $revealed[$device->id] = [
                                    'name' => $device->device_name,
                                    'secret' => is_string($existingSecret) ? $existingSecret : '',
                                    'provisioned' => false,
                                ];
                            }

                            continue;
                        }

                        $secret = null;

                        if (! $dryRun) {
                            $secret = bin2hex(random_bytes(32));
                            $config['webhook_secret'] = $secret;
                            $device->provider_config = $config;
                            $device->save();
                        }

                        $provisionedCount++;

                        if ($reveal && $secret !== null) {
                            $revealed[$device->id] = [
                                'name' => $device->device_name,
                                'secret' => $secret,
                                'provisioned' => true,
                            ];
                        }
                    } finally {
                        try {
                            $lock->release();
                        } catch (Throwable) {
                            // The lock TTL is the backstop.
                        }
                    }
                }
            });

        $action = $dryRun ? 'would be provisioned' : 'provisioned';

        $this->info(
            ($dryRun ? 'Dry run complete. ' : 'Provisioning complete. ')
            . "{$examinedCount} device(s) examined, {$provisionedCount} {$action}, {$alreadyConfiguredCount} already had a secret."
        );

        if ($unreadableCount > 0) {
            $this->error(sprintf(
                '%d device(s) have an unreadable provider_config or a non-string webhook secret and were left untouched. Re-run with --replace-unreadable to discard the existing value, or recover it with the original application key first.',
                $unreadableCount
            ));
        }

        if ($lockFailureCount > 0) {
            $this->error(sprintf(
                '%d device(s) were skipped because another change held their provider configuration lock. Re-run the command once those changes finish.',
                $lockFailureCount
            ));
        }

        if ($reveal) {
            if ($dryRun) {
                $this->warn('Dry run generated no secrets, so nothing is revealed. Re-run without --dry-run to provision and reveal.');
            } else {
                $this->renderRevealedSecrets($revealed);
            }
        } elseif ($provisionedCount > 0) {
            $this->warn("{$provisionedCount} secret(s) were provisioned but not shown. Re-run with --reveal to print them for device configuration.");
        }

        Log::info('Biometric webhook secrets provisioning complete', [
            'dry_run' => $dryRun,
            'reveal' => $reveal,
            'gym_id' => $gymId,
            'examined' => $examinedCount,
            'provisioned' => $provisionedCount,
            'already_configured' => $alreadyConfiguredCount,
            'unreadable_skipped' => $unreadableCount,
            'lock_failures' => $lockFailureCount,
        ]);

        return $unreadableCount > 0 || $lockFailureCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Print each device's signing secret so the operator can install it.
     *
     * Only reached under `--reveal`. The secret is the only thing that lets a
     * terminal produce a signature the webhook controller accepts, so it must
     * leave this process; the output is the delivery mechanism.
     *
     * @param  array<int, array{name: string, secret: string, provisioned: bool}>  $revealed
     */
    protected function renderRevealedSecrets(array $revealed): void
    {
        if ($revealed === []) {
            $this->line('No device secrets to reveal.');

            return;
        }

        $this->newLine();
        $this->line('Install the matching secret on each terminal (webhook signing key):');

        foreach ($revealed as $deviceId => $entry) {
            $state = $entry['provisioned'] ? 'newly provisioned' : 'already configured';
            $this->line(sprintf('  device %d (%s) [%s]: %s', $deviceId, $entry['name'], $state, $entry['secret']));
        }

        $this->newLine();
        $this->warn('Treat these values as credentials: a device that cannot sign will have its events rejected.');
    }

    /**
     * Normalize an Eloquent key for the typed provider-config lock helper.
     */
    private static function deviceKey(BiometricDevice $device): int|string
    {
        $key = $device->getKey();

        return is_int($key) || is_string($key) ? $key : 0;
    }
}
