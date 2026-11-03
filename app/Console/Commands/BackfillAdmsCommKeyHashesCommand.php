<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\Services\AdmsDeviceAuthenticator;
use App\Biometrics\Services\CommKeyReveal;
use App\Exceptions\StaleDeviceSnapshotException;
use App\Models\BiometricDevice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillAdmsCommKeyHashesCommand extends Command
{
    protected $signature = 'biometrics:backfill-adms-comm-keys
        {--dry-run : Preview changes without updating the database}
        {--provision-missing : Generate and provision 6-digit numeric keys for ADMS devices lacking a comm key}
        {--reprovision-legacy : Replace unsalted, out-of-range, or corrupt hashes with salted PBKDF2 (requires terminal reconfiguration)}
        {--reveal : Required for real provisioning; display newly generated keys (they are hashed immediately and cannot be recovered)}
        {--clean-plaintext : Remove legacy plaintext aliases only after the stored verifier is proven to accept the sanitized key}
        {--gym-id= : Scope backfill to a specific gym ID}
        {--device-id= : Scope backfill to a specific device ID}';

    protected $description = 'Migrate plaintext ADMS pushcommkeys, provision missing or legacy keys, and optionally clean verified plaintext';

    /**
     * Device ids already counted as refused, so a row that satisfies more than
     * one collector is not reported (or counted) twice.
     *
     * @var array<int, true>
     */
    private array $refusedIds = [];

    public function handle(AdmsDeviceAuthenticator $authenticator): int
    {
        $this->refusedIds = [];

        $dryRun = $this->option('dry-run');
        $provisionMissing = $this->option('provision-missing');
        $reprovisionLegacy = $this->option('reprovision-legacy');
        $reveal = $this->option('reveal');
        $cleanPlaintext = $this->option('clean-plaintext');

        $gymId = $this->positiveIntOption('gym-id');
        $deviceId = $this->positiveIntOption('device-id');

        if ($gymId === false || $deviceId === false) {
            $this->error('--gym-id and --device-id must be positive integers.');

            return self::FAILURE;
        }

        $migrated = 0;
        $normalized = 0;
        $refused = 0;
        $provisioned = 0;
        $failed = 0;
        $migrationFailed = 0;
        $cleanupFailed = 0;
        $wouldProvision = 0;
        $cleaned = 0;
        $wouldClean = 0;
        $retained = 0;
        $wouldRetain = 0;
        $mismatched = 0;
        $auditFailures = 0;
        /** @var array<int, BiometricDevice> $unkeyedDevices */
        $unkeyedDevices = [];
        /** @var array<int, BiometricDevice> $legacyDevices */
        $legacyDevices = [];
        /** @var array<int, array{id: int, serial: string|null, gym_id: int, key: string}> $provisionedTable */
        $provisionedTable = [];

        // Collect provisioning targets before any mutation. A refused --reveal
        // run must not migrate hashes or delete plaintext first.
        $this->collectUnkeyedDevices($unkeyedDevices, $gymId, $deviceId, $refused);
        $this->collectLegacyDevices($legacyDevices, $gymId, $deviceId, $refused);

        // Keyed by device id: a whitespace hash can satisfy both collectors and
        // must not be provisioned twice.
        $provisionTargets = [];

        if ($provisionMissing) {
            foreach ($unkeyedDevices as $id => $device) {
                $provisionTargets[$id] = $device;
            }
        }

        if ($reprovisionLegacy) {
            foreach ($legacyDevices as $id => $device) {
                $provisionTargets[$id] = $device;
            }

            // A whitespace-only plaintext placeholder has no verifier yet but
            // is still a legacy/provisioning target for --reprovision-legacy.
            foreach ($unkeyedDevices as $id => $device) {
                try {
                    if ($device->rawCommKeyAliases() !== []) {
                        $provisionTargets[$id] = $device;
                    }
                } catch (Throwable) {
                    // An unreadable row was already reported by the collector.
                }
            }
        }

        if (filled($provisionTargets) && ! $dryRun && ! $reveal) {
            $this->error('Refusing to provision keys without --reveal: generated keys are hashed immediately and cannot be recovered.');

            return self::FAILURE;
        }

        // 1. Migrate plaintext provider_config keys into salted PBKDF2 hashes.
        $migrationQuery = BiometricDevice::query()
            ->where('adms_enabled', true)
            ->where(function (Builder $query): void {
                $query->whereNull('adms_comm_key_hash')
                    ->orWhere('adms_comm_key_hash', '')
                    ->orWhereRaw("TRIM(adms_comm_key_hash) = ''");
            })
            ->whereNotNull('provider_config');

        $this->scopeQuery($migrationQuery, $gymId, $deviceId);

        $migrationQuery->chunkById(100, function ($devices) use ($dryRun, $provisionMissing, $reprovisionLegacy, $authenticator, &$migrated, &$normalized, &$refused, &$migrationFailed, &$unkeyedDevices, &$auditFailures): void {
            foreach ($devices as $device) {
                if ($device->providerConfigIsUnreadable()) {
                    if ($this->markRefused($device->id)) {
                        $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] provider_config could not be decrypted; left untouched.");
                        $refused++;
                    }

                    continue;
                }

                try {
                    $hasAmbiguousAliases = $device->hasAmbiguousCommKeyAliases();
                    $rawKey = $device->admsPlaintextCommKeyRaw();
                } catch (Throwable $exception) {
                    if ($this->markRefused($device->id)) {
                        $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] provider_config could not be decrypted: {$exception->getMessage()}");
                        $refused++;
                    }

                    continue;
                }

                if ($hasAmbiguousAliases) {
                    if ($this->markRefused($device->id)) {
                        $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] has conflicting pushcommkey and comm_key values; left untouched for operator review.");
                        $refused++;
                    }

                    continue;
                }

                if ($rawKey === null) {
                    if ($device->rawCommKeyAliases() !== [] && ! $provisionMissing && ! $reprovisionLegacy) {
                        if ($this->markRefused($device->id)) {
                            $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] has a comm key that normalizes to an empty string. Refused migration. Please re-enter the key or re-provision.");
                            $refused++;
                        }

                        continue;
                    }

                    $unkeyedDevices[$device->id] = $device;

                    continue;
                }

                // Refuse numeric keys stored as non-strings (e.g. JSON numbers that lost leading zeros or precision).
                if (! is_string($rawKey)) {
                    if ($this->markRefused($device->id)) {
                        $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] has a non-string numeric comm key in provider_config. Refused migration to prevent bricking device. Please re-enter key as string or re-provision.");
                        $refused++;
                    }

                    continue;
                }

                $sanitizedKey = BiometricDevice::sanitizeCommKey($rawKey);

                if ($sanitizedKey === '') {
                    // --provision-missing and --reprovision-legacy both
                    // supersede a whitespace-only placeholder by generating a
                    // fresh key; do not report it as both refused and
                    // provisioned.
                    if ($provisionMissing || $reprovisionLegacy) {
                        $unkeyedDevices[$device->id] = $device;

                        continue;
                    }

                    if ($this->markRefused($device->id)) {
                        $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] has a comm key that normalizes to an empty string. Refused migration. Please re-enter the key or re-provision.");
                        $refused++;
                    }

                    continue;
                }

                if ($sanitizedKey !== $rawKey) {
                    $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] comm key contains whitespace; migrating its normalized form.");
                    $normalized++;
                }

                if ($dryRun) {
                    $this->info("Device [ID: {$device->id}, SN: {$device->device_serial}] would migrate pushcommkey hash.");
                    $migrated++;

                    continue;
                }

                $salt = BiometricDevice::generateCommKeySalt();
                $hash = BiometricDevice::hashCommKey($sanitizedKey, $salt);

                try {
                    DB::transaction(function () use ($device, $hash, $salt): void {
                        $locked = $this->lockUnchangedDevice($device);
                        $config = is_array($locked->provider_config) ? $locked->provider_config : [];

                        unset($config['pushcommkey']);

                        if ($locked->mayDeleteCommKeyAlias()) {
                            unset($config['comm_key']);
                        }

                        $locked->adms_comm_key_hash = $hash;
                        $locked->adms_comm_key_salt = $salt;
                        $locked->provider_config = $config;
                        $locked->save();
                    });
                } catch (Throwable $exception) {
                    // The observer runs after the commit; a failure there must
                    // not be reported as "left unchanged" when the verifier is
                    // already in the database.
                    $committed = false;

                    try {
                        $committed = BiometricDevice::query()
                            ->whereKey($device->getKey())
                            ->where('adms_comm_key_hash', $hash)
                            ->exists();
                    } catch (Throwable) {
                        // Fall through as a failure.
                    }

                    if (! $committed) {
                        $migrationFailed++;
                        $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] migration failed and was left unchanged: {$exception->getMessage()}");

                        continue;
                    }

                    $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] migration committed but a post-commit step failed; treating it as migrated: {$exception->getMessage()}");
                }

                $authenticator->resetFailures($device);

                // Migrating an unchanged plaintext key into a verifier does
                // not invalidate a pending reveal: it still shows the current
                // key. A real rotation/provision is what supersedes it.
                try {
                    activity()
                        ->performedOn($device)
                        ->withProperties([
                            'gym_id' => $device->gym_id,
                            'action' => 'adms_comm_key_migrated',
                        ])
                        ->log('ADMS device comm key migrated to PBKDF2 hash');
                } catch (Throwable $exception) {
                    $auditFailures++;
                    $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] migration committed but the activity log failed: {$exception->getMessage()}");
                }

                $this->info("Device [ID: {$device->id}, SN: {$device->device_serial}] migrated pushcommkey hash.");
                $migrated++;
            }
        });

        // 2. Remove stale plaintext copies only when the sanitized key is proven
        //    to verify against the stored verifier. Raw-only matches are re-hashed
        //    first; mismatches and ambiguous aliases are left for review.
        if ($cleanPlaintext) {
            $cleanupQuery = BiometricDevice::query()
                ->where('adms_enabled', true)
                ->whereNotNull('adms_comm_key_hash')
                ->whereNotNull('provider_config');

            $this->scopeQuery($cleanupQuery, $gymId, $deviceId);

            $cleanupQuery->chunkById(100, function ($devices) use ($dryRun, $authenticator, &$cleaned, &$wouldClean, &$retained, &$wouldRetain, &$mismatched, &$cleanupFailed, &$auditFailures): void {
                foreach ($devices as $device) {
                    if (! $device->hasRealCommKeyHash()) {
                        continue;
                    }

                    if ($device->providerConfigIsUnreadable()) {
                        if ($this->markRefused($device->id)) {
                            $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] provider_config could not be decrypted; left untouched.");
                            $mismatched++;
                        }

                        continue;
                    }

                    try {
                        $hasAmbiguousAliases = $device->hasAmbiguousCommKeyAliases();
                        $aliases = $device->admsPlaintextCommKeyAliases();
                        // The aliases map was already filtered to non-blank
                        // values, so the accessor cannot return null here.
                        $plainKey = (string) $device->admsPlaintextCommKey();
                        $rawConfig = is_array($device->provider_config) ? $device->provider_config : [];
                    } catch (Throwable $exception) {
                        if ($this->markRefused($device->id)) {
                            $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] provider_config could not be decrypted: {$exception->getMessage()}");
                            $mismatched++;
                        }

                        continue;
                    }

                    if ($hasAmbiguousAliases) {
                        if ($this->markRefused($device->id)) {
                            $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] has conflicting plaintext aliases; left in place for operator review.");
                            $mismatched++;
                        }

                        continue;
                    }

                    $rawAliasKeys = array_values(array_intersect(array_keys($rawConfig), ['pushcommkey', 'comm_key']));

                    if ($rawAliasKeys === []) {
                        continue;
                    }

                    // A cloud provider's `comm_key` is a provider credential,
                    // not an ADMS alias. It never enters $aliases, so account
                    // for it here instead of losing it from the audit totals.
                    if ($aliases === []) {
                        // Blank placeholders carry no credential; do not count
                        // them as a provider-policy retention.
                        $filledRawAliases = $this->filledCommKeyAliases($rawConfig);

                        if ($filledRawAliases === []) {
                            continue;
                        }

                        $this->warn(sprintf(
                            'Device [ID: %d, SN: %s] has plaintext alias(es) %s retained by provider policy; not counted as cleaned.',
                            $device->id,
                            $device->device_serial ?? 'n/a',
                            implode(', ', $rawAliasKeys)
                        ));

                        if ($dryRun) {
                            $wouldRetain++;
                        } else {
                            $retained++;
                        }

                        continue;
                    }

                    $proven = $device->verifyCommKeyWithoutLegacyFallback($plainKey);

                    if (! $proven) {
                        if ($device->verifyUsingLegacyFallback($plainKey)) {
                            if ($dryRun) {
                                $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] would be re-hashed from the raw fallback to the sanitized form before cleaning the plaintext.");
                                $proven = true;
                            } else {
                                $device->healLegacyCommKey($plainKey);

                                // The self-heal performs a conditional UPDATE
                                // that bumps updated_at. Re-read so the lock
                                // compares the row the cleanup will write, not
                                // the pre-heal snapshot.
                                try {
                                    $device->refresh();
                                } catch (Throwable $exception) {
                                    $cleanupFailed++;
                                    $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] could not be re-read after the verifier self-heal; left uncleaned: {$exception->getMessage()}");

                                    continue;
                                }

                                $proven = $device->verifyCommKeyWithoutLegacyFallback($plainKey);

                                if ($proven) {
                                    $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] matched only through the raw fallback; re-hashed the sanitized form before cleaning the plaintext.");
                                }
                            }
                        }
                    }

                    if (! $proven) {
                        if ($this->markRefused($device->id)) {
                            $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] has legacy plaintext that does not match its hash; left in place for operator review.");
                            $mismatched++;
                        }

                        continue;
                    }

                    $removable = $device->removableCommKeyAliases($rawConfig);
                    $preserved = array_values(array_diff($this->filledCommKeyAliases($rawConfig), $removable));

                    if ($removable === []) {
                        // Defensive: only a value that provider policy protects
                        // remains. Count it as retained, never as cleaned.
                        $this->warn(sprintf(
                            'Device [ID: %d, SN: %s] has plaintext alias(es) %s retained by provider policy; not counted as cleaned.',
                            $device->id,
                            $device->device_serial ?? 'n/a',
                            implode(', ', $preserved)
                        ));

                        if ($dryRun) {
                            $wouldRetain++;
                        } else {
                            $retained++;
                        }

                        continue;
                    }

                    if ($preserved !== []) {
                        $this->warn(sprintf(
                            'Device [ID: %d, SN: %s] will remove %s and retain %s by provider policy.',
                            $device->id,
                            $device->device_serial ?? 'n/a',
                            implode(', ', $removable),
                            implode(', ', $preserved)
                        ));
                    }

                    if ($dryRun) {
                        $this->info("Device [ID: {$device->id}, SN: {$device->device_serial}] would remove verified legacy plaintext.");
                        $wouldClean++;

                        if ($preserved !== []) {
                            $wouldRetain++;
                        }

                        continue;
                    }

                    /** @var array{removed: list<string>, retained: list<string>}|null $cleanupResult */
                    $cleanupResult = null;

                    try {
                        DB::transaction(function () use ($device, &$cleanupResult): void {
                            $locked = $this->lockUnchangedDevice($device);
                            $config = is_array($locked->provider_config) ? $locked->provider_config : [];

                            // Recompute from the locked row, never from the
                            // chunk snapshot: a concurrent native→cloud change
                            // leaves provider_config untouched, and the locked
                            // row may protect `comm_key` as a cloud credential.
                            $removable = $locked->removableCommKeyAliases($config);

                            if ($removable === []) {
                                throw new StaleDeviceSnapshotException(sprintf(
                                    'Device [ID: %d] no longer needs plaintext cleanup (aliases were removed or provider policy changed).',
                                    $locked->id
                                ));
                            }

                            $retainedAliases = array_values(array_diff($this->filledCommKeyAliases($config), $removable));

                            foreach ($removable as $alias) {
                                unset($config[$alias]);
                            }

                            $locked->provider_config = $config;
                            $cleanupResult = ['removed' => $removable, 'retained' => $retainedAliases];
                            $locked->save();
                        });
                    } catch (StaleDeviceSnapshotException $exception) {
                        $cleanupFailed++;
                        $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] cleanup conflict; left unchanged: {$exception->getMessage()}");

                        continue;
                    } catch (Throwable $exception) {
                        $committed = false;

                        try {
                            $fresh = BiometricDevice::query()->find($device->getKey());

                            if ($fresh instanceof BiometricDevice && ! $fresh->providerConfigIsUnreadable()) {
                                $configAfter = is_array($fresh->provider_config) ? $fresh->provider_config : [];
                                $committed = $cleanupResult !== null
                                    && array_intersect($cleanupResult['removed'], array_keys($configAfter)) === [];
                            }
                        } catch (Throwable) {
                            // Fall through as a failure.
                        }

                        if (! $committed) {
                            $cleanupFailed++;
                            $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] cleanup failed and was left unchanged: {$exception->getMessage()}");

                            continue;
                        }

                        $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] cleanup committed but a post-commit step failed; treating it as cleaned: {$exception->getMessage()}");
                    }

                    $removedAliases = is_array($cleanupResult['removed'] ?? null) ? $cleanupResult['removed'] : [];
                    $retainedAliases = is_array($cleanupResult['retained'] ?? null) ? $cleanupResult['retained'] : [];

                    if ($removedAliases === []) {
                        $cleanupFailed++;
                        $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] cleanup committed but removed no alias; left unchanged.");

                        continue;
                    }

                    $authenticator->resetFailures($device);
                    $cleaned++;

                    if ($retainedAliases !== []) {
                        $retained++;
                    }

                    try {
                        activity()
                            ->performedOn($device)
                            ->withProperties([
                                'gym_id' => $device->gym_id,
                                'action' => 'adms_comm_key_plaintext_cleaned',
                                'removed_aliases' => $removedAliases,
                                'retained_aliases' => $retainedAliases,
                                'provider_type' => $device->provider_type?->value,
                            ])
                            ->log('ADMS legacy plaintext comm key removed after hash was established');
                    } catch (Throwable $exception) {
                        $auditFailures++;
                        $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] cleanup committed but the activity log failed: {$exception->getMessage()}");
                    }
                }
            });
        }

        // 3. Provisioning. Missing-key and legacy re-provisioning are separate opt-ins.
        try {
            foreach ($provisionTargets as $device) {
                // Re-read after migration: a whitespace hash plus plaintext can
                // be migrated in step 1, and the pre-migration snapshot must not
                // rotate it again with a second generated key.
                try {
                    $device->refresh();
                } catch (Throwable $exception) {
                    $failed++;
                    $this->error(sprintf(
                        'Device [ID: %d] could not be re-read before provisioning: %s',
                        $device->id,
                        $exception->getMessage()
                    ));

                    continue;
                }

                $hasPlaceholderAlias = ! $device->hasRealCommKeyHash()
                    && $device->rawCommKeyAliases() !== [];
                $isLegacy = $reprovisionLegacy
                    && ($device->commKeyVerifierNeedsReprovision() || $hasPlaceholderAlias);
                $isMissing = $provisionMissing && ! $device->hasRealCommKeyHash();

                if (! $isLegacy && ! $isMissing) {
                    continue;
                }

                if ($dryRun) {
                    $provisionedTable[] = [
                        'id' => $device->id,
                        'serial' => $device->device_serial,
                        'gym_id' => $device->gym_id,
                        'key' => '(dry run — not generated)',
                    ];
                    $wouldProvision++;

                    continue;
                }

                $plainKey = BiometricDevice::generateCommKey();
                $salt = BiometricDevice::generateCommKeySalt();
                $hash = BiometricDevice::hashCommKey($plainKey, $salt);

                try {
                    DB::transaction(function () use ($device, $hash, $salt, $isLegacy): void {
                        $locked = $this->lockUnchangedDevice($device);
                        $config = is_array($locked->provider_config) ? $locked->provider_config : [];
                        $configChanged = false;

                        if ($isLegacy) {
                            // A rotation supersedes every native alias, not only
                            // the empty ones; otherwise the old plaintext remains
                            // and later cleanup reports a permanent mismatch.
                            foreach (['pushcommkey', 'comm_key'] as $alias) {
                                if (! array_key_exists($alias, $config)) {
                                    continue;
                                }

                                if ($alias === 'comm_key' && ! $locked->mayDeleteCommKeyAlias()) {
                                    continue;
                                }

                                unset($config[$alias]);
                                $configChanged = true;
                            }
                        } else {
                            if (array_key_exists('pushcommkey', $config) && BiometricDevice::sanitizeCommKey($config['pushcommkey']) === '') {
                                unset($config['pushcommkey']);
                                $configChanged = true;
                            }

                            if ($locked->mayDeleteCommKeyAlias()
                                && array_key_exists('comm_key', $config)
                                && BiometricDevice::sanitizeCommKey($config['comm_key']) === '') {
                                unset($config['comm_key']);
                                $configChanged = true;
                            }
                        }

                        if ($configChanged) {
                            $locked->provider_config = $config;
                        }

                        $locked->adms_comm_key_hash = $hash;
                        $locked->adms_comm_key_salt = $salt;
                        $locked->save();
                    });
                } catch (Throwable $exception) {
                    // The observer may throw after the transaction commit. If
                    // the verifier is already in the database, the key is valid
                    // and must still be printed; only a real commit failure
                    // leaves the device unchanged.
                    $committed = false;

                    try {
                        $committed = BiometricDevice::query()
                            ->whereKey($device->getKey())
                            ->where('adms_comm_key_hash', $hash)
                            ->exists();
                    } catch (Throwable) {
                        // Fall through as a failure; the key cannot be verified
                        // as committed.
                    }

                    if (! $committed) {
                        $failed++;
                        $this->error(sprintf(
                            'Device [ID: %d, SN: %s] provisioning failed and was left unchanged: %s',
                            $device->id,
                            $device->device_serial ?? 'n/a',
                            $exception->getMessage()
                        ));

                        continue;
                    }

                    $this->warn(sprintf(
                        'Device [ID: %d, SN: %s] verifier committed but a post-commit step failed; printing the key anyway: %s',
                        $device->id,
                        $device->device_serial ?? 'n/a',
                        $exception->getMessage()
                    ));
                }

                // Emit the key before any secondary work (activity logging can
                // fail) so a later exception cannot strand a saved hash whose
                // plaintext was never displayed.
                $this->line(sprintf(
                    'PROVISIONED KEY  Device ID: %d  SN: %s  Gym ID: %d  Key: %s',
                    $device->id,
                    $device->device_serial ?? 'n/a',
                    $device->gym_id,
                    $plainKey
                ));

                // A superseded reveal must not outlive the rotation.
                CommKeyReveal::forget($device->id);
                $authenticator->resetFailures($device);

                $provisionedTable[] = [
                    'id' => $device->id,
                    'serial' => $device->device_serial,
                    'gym_id' => $device->gym_id,
                    'key' => $plainKey,
                ];

                $provisioned++;

                try {
                    activity()
                        ->performedOn($device)
                        ->withProperties([
                            'gym_id' => $device->gym_id,
                            'action' => $isLegacy ? 'adms_comm_key_reprovisioned' : 'adms_comm_key_provisioned',
                        ])
                        ->log($isLegacy
                            ? 'ADMS device legacy comm key re-provisioned with PBKDF2 hash'
                            : 'ADMS device comm key provisioned with PBKDF2 hash');
                } catch (Throwable $exception) {
                    // The transaction committed and the key was printed. An audit
                    // failure must not be reported as a provisioning failure.
                    $auditFailures++;
                    $this->warn(sprintf(
                        'Device [ID: %d, SN: %s] key was provisioned and printed, but the activity log failed: %s',
                        $device->id,
                        $device->device_serial ?? 'n/a',
                        $exception->getMessage()
                    ));
                }
            }
        } finally {
            if (filled($provisionedTable)) {
                $this->newLine();

                if ($dryRun) {
                    $this->warn('--- DRY RUN: devices that WOULD be provisioned (no keys were generated) ---');
                } else {
                    $this->info('--- Provisioned ADMS Device Keys (configure on physical terminals now) ---');
                }

                $this->table(
                    ['Device ID', 'Device Serial', 'Gym ID', 'Provisioned Comm Key'],
                    $provisionedTable
                );
            }
        }

        if (! $provisionMissing && filled($unkeyedDevices)) {
            $this->newLine();
            $this->warn(sprintf(
                'Notice: %d adms_enabled device(s) have no comm key configured. Run with `--provision-missing` to generate 6-digit keys before enforcing ADMS_REQUIRE_COMM_KEY.',
                count($unkeyedDevices)
            ));
            $this->table(
                ['Device ID', 'Device Serial', 'Gym ID'],
                array_map(fn ($d) => ['id' => $d->id, 'serial' => $d->device_serial, 'gym_id' => $d->gym_id], array_values($unkeyedDevices))
            );
        }

        if (! $reprovisionLegacy && filled($legacyDevices)) {
            $this->newLine();
            $this->warn(sprintf(
                'Notice: %d adms_enabled device(s) have a legacy or unsafe comm key verifier (no salt, an out-of-range iteration count, or an unrecognized hash shape). Run with `--reprovision-legacy` to generate new PBKDF2 salted keys.',
                count($legacyDevices)
            ));
            $this->table(
                ['Device ID', 'Device Serial', 'Gym ID'],
                array_map(fn ($d) => ['id' => $d->id, 'serial' => $d->device_serial, 'gym_id' => $d->gym_id], array_values($legacyDevices))
            );
        }

        if (! $cleanPlaintext) {
            $stalePlaintext = BiometricDevice::query()
                ->where('adms_enabled', true)
                ->whereNotNull('adms_comm_key_hash')
                ->whereNotNull('provider_config');

            $this->scopeQuery($stalePlaintext, $gymId, $deviceId);

            $staleCount = 0;
            $unreadableCount = 0;

            foreach ($stalePlaintext->cursor() as $device) {
                if ($device->providerConfigIsUnreadable()) {
                    $unreadableCount++;

                    continue;
                }

                try {
                    if ($device->hasRealCommKeyHash() && $device->admsPlaintextCommKey() !== null) {
                        $staleCount++;
                    }
                } catch (Throwable) {
                    $unreadableCount++;
                }
            }

            if ($staleCount > 0) {
                $this->warn(sprintf(
                    'Notice: %d adms_enabled device(s) still have legacy plaintext aliases. Run with `--clean-plaintext` after the backfill to remove verified copies.',
                    $staleCount
                ));
            }

            if ($unreadableCount > 0) {
                $this->warn(sprintf(
                    'Notice: %d adms_enabled device(s) have an unreadable provider_config; re-enter or rotate their comm key.',
                    $unreadableCount
                ));
            }
        }

        if ($provisioned > 0 && ! $dryRun) {
            $this->warn('Provisioned keys were displayed above as they were generated. They are hashed in the database and cannot be recovered; capture them now.');
        }

        if ($failed > 0) {
            $this->error(sprintf('%d device(s) failed provisioning and were left unchanged; re-run with --provision-missing or --reprovision-legacy after fixing the error.', $failed));
        }

        if ($migrationFailed + $cleanupFailed > 0) {
            $this->error(sprintf(
                '%d device(s) failed a migration or cleanup write and were left unchanged; re-run the backfill after fixing the error.',
                $migrationFailed + $cleanupFailed
            ));
        }

        if ($mismatched > 0) {
            $this->error(sprintf('%d device(s) have plaintext that could not be proven safe to delete; review them before disabling the raw-whitespace fallback.', $mismatched));
        }

        if ($auditFailures > 0) {
            $this->warn(sprintf('%d device(s) were provisioned and printed, but their activity-log entry failed. The keys are valid; the audit gap is separate from provisioning.', $auditFailures));
        }

        $retainedCount = $dryRun ? $wouldRetain : $retained;

        $summary = sprintf(
            'ADMS comm key backfill completed. %d migrated (%d normalized), %d refused, %d provisioned (%d failed), %d stale plaintext cleaned%s.%s',
            $migrated,
            $normalized,
            $refused,
            $dryRun ? $wouldProvision : $provisioned,
            $failed,
            $dryRun ? $wouldClean : $cleaned,
            $dryRun ? ' (dry run)' : '',
            $retainedCount > 0 ? " {$retainedCount} retained by provider policy." : ''
        );
        $this->info($summary);

        return ($refused > 0 || $mismatched > 0 || $failed > 0 || $migrationFailed > 0 || $cleanupFailed > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @param  array<int, BiometricDevice>  $unkeyedDevices
     */
    private function collectUnkeyedDevices(array &$unkeyedDevices, ?int $gymId, ?int $deviceId, int &$refused): void
    {
        $query = BiometricDevice::query()
            ->where('adms_enabled', true)
            ->where(function (Builder $builder): void {
                $builder->whereNull('adms_comm_key_hash')
                    ->orWhere('adms_comm_key_hash', '')
                    ->orWhereRaw("TRIM(adms_comm_key_hash) = ''");
            });

        $this->scopeQuery($query, $gymId, $deviceId);

        $query->chunkById(100, function ($devices) use (&$unkeyedDevices, &$refused): void {
            foreach ($devices as $device) {
                try {
                    if ($device->providerConfigIsUnreadable()) {
                        if ($this->markRefused($device->id)) {
                            $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] provider_config could not be decrypted during collection; left untouched.");
                            $refused++;
                        }

                        continue;
                    }

                    if (! $device->hasCommKeyConfigured()) {
                        $unkeyedDevices[$device->id] = $device;
                    }
                } catch (Throwable $exception) {
                    // One corrupt provider_config row must not abort the whole
                    // scan before the command has a chance to report anything.
                    if ($this->markRefused($device->id)) {
                        $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] provider_config could not be decrypted during collection: {$exception->getMessage()}");
                        $refused++;
                    }
                }
            }
        });
    }

    /**
     * @param  array<int, BiometricDevice>  $legacyDevices
     */
    private function collectLegacyDevices(array &$legacyDevices, ?int $gymId, ?int $deviceId, int &$refused): void
    {
        $query = BiometricDevice::query()
            ->where('adms_enabled', true)
            ->whereNotNull('adms_comm_key_hash');

        $this->scopeQuery($query, $gymId, $deviceId);

        $query->chunkById(100, function ($devices) use (&$legacyDevices, &$refused): void {
            foreach ($devices as $device) {
                if (! $device->commKeyVerifierNeedsReprovision()) {
                    continue;
                }

                if ($device->providerConfigIsUnreadable()) {
                    if ($this->markRefused($device->id)) {
                        $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] provider_config could not be decrypted during legacy collection; left untouched.");
                        $refused++;
                    }

                    continue;
                }

                try {
                    if ($device->hasAmbiguousCommKeyAliases()) {
                        if ($this->markRefused($device->id)) {
                            $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] has conflicting pushcommkey and comm_key values; left untouched for operator review.");
                            $refused++;
                        }

                        continue;
                    }
                } catch (Throwable $exception) {
                    if ($this->markRefused($device->id)) {
                        $this->error("Device [ID: {$device->id}, SN: {$device->device_serial}] provider_config could not be decrypted during legacy collection: {$exception->getMessage()}");
                        $refused++;
                    }

                    continue;
                }

                $legacyDevices[$device->id] = $device;
            }
        });
    }

    /**
     * Lock and prove the row still matches the values used for the decision.
     * A concurrent rotation, provider-policy change, or unrelated edit must
     * win, never be overwritten by a stale chunk snapshot.
     */
    private function lockUnchangedDevice(BiometricDevice $device): BiometricDevice
    {
        $deviceKey = $device->getKey();
        $deviceKey = is_scalar($deviceKey) ? (string) $deviceKey : 'unknown';

        $locked = BiometricDevice::query()
            ->whereKey($device->getKey())
            ->lockForUpdate()
            ->first();

        throw_unless($locked instanceof BiometricDevice, StaleDeviceSnapshotException::class, "Device [ID: {$deviceKey}] no longer exists.");

        $snapshotAttributes = [
            'adms_comm_key_hash',
            'adms_comm_key_salt',
            'provider_config',
            'provider_type',
            'adms_enabled',
            'updated_at',
        ];

        foreach ($snapshotAttributes as $attribute) {
            throw_if($this->snapshotValue($locked->getRawOriginal($attribute)) !== $this->snapshotValue($device->getRawOriginal($attribute)), StaleDeviceSnapshotException::class, "Device [ID: {$deviceKey}] changed concurrently; left unchanged.");
        }

        return $locked;
    }

    /**
     * Normalize raw attribute values so a hydrated string and an in-memory
     * enum/bool representation compare by value rather than PHP type.
     *
     * Callers pass `getRawOriginal()` values only. `updated_at` is stored at
     * second precision, so two writes within the same second compare equal
     * here; the remaining snapshot columns still detect a conflicting
     * credential or config write.
     */
    private function snapshotValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return serialize($value);
    }

    /**
     * Alias names in the config that carry a non-blank credential.
     *
     * @param  array<array-key, mixed>  $config
     * @return list<string>
     */
    private function filledCommKeyAliases(array $config): array
    {
        $aliases = [];

        foreach (['pushcommkey', 'comm_key'] as $alias) {
            if (! array_key_exists($alias, $config)) {
                continue;
            }

            $value = $config[$alias];

            if ((is_string($value) || is_numeric($value)) && BiometricDevice::sanitizeCommKey((string) $value) !== '') {
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }

    private function markRefused(int $deviceId): bool
    {
        if (isset($this->refusedIds[$deviceId])) {
            return false;
        }

        $this->refusedIds[$deviceId] = true;

        return true;
    }

    /**
     * @param  Builder<BiometricDevice>  $query
     */
    private function scopeQuery(Builder $query, ?int $gymId, ?int $deviceId): void
    {
        if ($gymId !== null) {
            $query->where('gym_id', $gymId);
        }

        if ($deviceId !== null) {
            $query->where('id', $deviceId);
        }
    }

    /**
     * @return int|null|false Null when absent, false when invalid.
     */
    private function positiveIntOption(string $name): int|null|false
    {
        $value = $this->option($name);

        if (blank($value)) {
            return null;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? false : $validated;
    }
}
