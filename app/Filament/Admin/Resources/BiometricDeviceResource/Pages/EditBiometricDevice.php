<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BiometricDeviceResource\Pages;

use App\Biometrics\Services\AdmsDeviceAuthenticator;
use App\Biometrics\Services\CommKeyReveal;
use App\Biometrics\Services\WebhookSecretReveal;
use App\Biometrics\Services\WebhookSecretRotationService;
use App\Enums\BiometricProvidersEnum;
use App\Filament\Admin\Resources\BiometricDeviceResource;
use App\Models\BiometricDevice;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @property BiometricDevice $record
 */
class EditBiometricDevice extends EditRecord
{
    protected static string $resource = BiometricDeviceResource::class;

    protected ?string $updatedCommKey = null;

    protected ?string $updatedCommKeyHash = null;

    protected ?string $updatedCommKeySalt = null;

    protected bool $commKeyRevealFailed = false;

    protected bool $removedCommKeyAlias = false;

    protected bool $providerConfigUnreadable = false;

    protected bool $providerConfigPreserved = false;

    protected bool $revealPublished = false;

    protected bool $manualWebhookSecretSubmitted = false;

    /**
     * The submitted provider_config after secret stripping, or null when the
     * form did not submit one. The merge with the freshly-read config happens
     * inside the provider-config lock.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $submittedProviderConfig = null;

    protected bool $replaceProviderConfig = false;

    /**
     * `provider_config` is hidden from serialization, so Filament's default
     * form fill would leave every nested provider field blank. Populate only
     * the non-secret fields the form renders for the selected provider.
     *
     * Filament's `$data` property is public and serialized into
     * `wire:snapshot`; hydrating the whole decrypted config would expose the
     * legacy `pushcommkey`/`comm_key` aliases, the webhook HMAC secret, and
     * provider API credentials in the page source. Secret inputs stay blank
     * with "leave blank to keep" semantics.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->providerConfigUnreadable = $this->record->providerConfigIsUnreadable();

        $config = $this->record->provider_config;
        /** @var array<string, mixed> $config */
        $config = is_array($config) ? $config : [];
        $data['provider_config'] = $this->providerConfigForForm($config);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function providerConfigForForm(array $config): array
    {
        $provider = $this->record->provider_type;
        $fields = BiometricDeviceResource::providerConfigFormFields($provider);

        // The signature header is not a secret. The webhook secret, custom
        // API key/secret, custom headers, and the legacy ADMS aliases are
        // deliberately never hydrated.
        $allowed = array_merge(BiometricDeviceResource::providerConfigCommonFields(), $fields);

        return array_intersect_key($config, array_flip($allowed));
    }

    private static function providerValue(mixed $provider): string
    {
        if ($provider instanceof BiometricProvidersEnum) {
            return $provider->value;
        }

        return is_string($provider) ? $provider : '';
    }

    /**
     * Compare provider policy, not the wire representation.
     *
     * `null` and DeviceNative are the same device-native policy, so an
     * explicit null→device_native selection must not look like a provider
     * change that requires discarding an unreadable configuration.
     */
    private static function providerSelectionKey(mixed $provider): string
    {
        if (BiometricDevice::providerTypeIsDeviceNative($provider)) {
            return BiometricProvidersEnum::DeviceNative->value;
        }

        return self::providerValue($provider);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->removedCommKeyAlias = false;
        $this->providerConfigPreserved = false;
        $this->manualWebhookSecretSubmitted = false;
        $this->submittedProviderConfig = null;
        $this->replaceProviderConfig = false;
        $this->updatedCommKey = null;
        $this->updatedCommKeyHash = null;
        $this->updatedCommKeySalt = null;

        // Protected Livewire state does not survive between the mount request
        // and the save request, so recompute the unreadable state from the
        // record rather than relying on mutateFormDataBeforeFill().
        $this->providerConfigUnreadable = $this->record->providerConfigIsUnreadable();

        $replaceProviderConfig = (bool) ($data['replace_unreadable_provider_config'] ?? false);
        unset($data['replace_unreadable_provider_config']);
        $this->replaceProviderConfig = $replaceProviderConfig;

        $currentProviderKey = self::providerSelectionKey($this->record->provider_type);
        $submittedProviderKey = self::providerSelectionKey($data['provider_type'] ?? $this->record->provider_type);

        if ($this->providerConfigUnreadable
            && ! $replaceProviderConfig
            && $submittedProviderKey !== $currentProviderKey) {
            throw ValidationException::withMessages([
                'data.provider_type' => 'The stored provider configuration cannot be decrypted. Tick "Replace unreadable provider configuration" before changing the provider type.',
            ]);
        }

        // Normalize empty serial string to null to avoid unique key collision
        if (array_key_exists('device_serial', $data)) {
            $rawSerial = $data['device_serial'];
            $data['device_serial'] = blank($rawSerial)
                ? null
                : trim(is_scalar($rawSerial) ? (string) $rawSerial : '');
        }

        $providerType = $data['provider_type'] ?? null;

        // A native/dual-mode device may keep its only verifier in
        // provider_config['comm_key']. Switching to a cloud provider stops
        // reading that alias, so a blank key would leave the terminal with a
        // 401 and no obvious cause. Require an explicit replacement. `pushcommkey`
        // is a valid alias for cloud providers too, so it never triggers this.
        if (
            (bool) ($data['adms_enabled'] ?? $this->record->adms_enabled)
            && $this->record->hasOnlyPlaintextCommKeyAlias()
            && BiometricDevice::providerTypeIsDeviceNative($this->record->provider_type)
            && ! BiometricDevice::providerTypeIsDeviceNative($providerType)
            && blank($data['adms_comm_key'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'data.adms_comm_key' => 'Enter a new ADMS Communication Key before switching away from device-native. The existing plaintext comm_key alias is not read for a cloud provider.',
            ]);
        }

        if ($this->providerConfigUnreadable && ! $replaceProviderConfig) {
            // The ciphertext may be recoverable with a backup or an old APP_KEY.
            // Never replace it on an unrelated edit (rename, status, key
            // rotation), including the cloud-provider defaults below. The
            // operator must opt in explicitly.
            unset($data['provider_config']);
            $this->providerConfigPreserved = true;
        } else {
            /** @var array<string, mixed>|null $submittedConfig */
            $submittedConfig = null;

            if (isset($data['provider_config']) && is_array($data['provider_config'])) {
                /** @var array<string, mixed> $submittedConfig */
                $submittedConfig = $data['provider_config'];

                // Secret inputs are never hydrated into the form. A blank
                // submission means "leave the stored value alone", not "erase
                // the credential".
                foreach (BiometricDeviceResource::providerConfigSecretFields() as $secretField) {
                    if (array_key_exists($secretField, $submittedConfig) && blank($submittedConfig[$secretField])) {
                        unset($submittedConfig[$secretField]);
                    }
                }

                $this->manualWebhookSecretSubmitted = BiometricDeviceResource::manualWebhookSecretWasSubmitted($submittedConfig);

                // Provenance is server-derived by the model when the provider
                // type changes; never accept it from Livewire state.
                unset($submittedConfig['comm_key_source']);
            }

            $this->submittedProviderConfig = $submittedConfig;

            // The submitted fields are merged with the freshly-read config
            // inside the provider-config lock in handleRecordUpdate(); merging
            // here would race another writer.
            unset($data['provider_config']);
        }

        if (filled($data['adms_comm_key'] ?? null)) {
            $rawKey = BiometricDevice::sanitizeCommKey($data['adms_comm_key']);
            $validationError = BiometricDevice::commKeyValidationError($rawKey);

            if ($validationError !== null) {
                throw ValidationException::withMessages(['data.adms_comm_key' => $validationError]);
            }

            $this->updatedCommKey = $rawKey;
            $this->updatedCommKeySalt = BiometricDevice::generateCommKeySalt();
            $this->updatedCommKeyHash = BiometricDevice::hashCommKey($rawKey, $this->updatedCommKeySalt);

            unset($data['adms_comm_key']);
        }

        // A blank key field is "keep the current key": leave provider_config
        // untouched. The backfill owns verified plaintext cleanup.
        return $data;
    }

    /**
     * Install the verifier in the same UPDATE as the rest of the record.
     *
     * The previous afterSave() write could fail independently and leave the
     * device with the old key while the terminal held the new one.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $save = function () use ($record, $data): Model {
            /** @var BiometricDevice $record */
            $data = $this->prepareProviderConfigForUpdate($record, $data);

            // fill() drops virtual form inputs such as `adms_comm_key`; only the
            // non-mass-assignable verifier is written with forceFill().
            $record->fill($data);

            if ($this->updatedCommKeyHash !== null && $this->updatedCommKeySalt !== null) {
                $record->forceFill([
                    'adms_comm_key_hash' => $this->updatedCommKeyHash,
                    'adms_comm_key_salt' => $this->updatedCommKeySalt,
                ]);
            }

            $record->save();

            if ($this->manualWebhookSecretSubmitted) {
                // The lock is still held, so a concurrent Generate cannot
                // promote its new reveal between this save and this forget. A
                // late post-commit forget could delete that newer reveal.
                WebhookSecretReveal::forget(self::deviceKey($record));
            }

            return $record;
        };

        if ($this->providerConfigPreserved) {
            // The unreadable ciphertext is preserved and provider_config is not
            // written, so no provider-config lock is needed.
            return $save();
        }

        // Every write that touches provider_config must own the per-device
        // provider-config lock. Fail fast rather than waiting: the operator can
        // retry, and a queued save would still race the form state.
        try {
            $lock = Cache::lock(
                WebhookSecretRotationService::providerConfigLockKey(self::deviceKey($record)),
                WebhookSecretRotationService::LOCK_TTL_SECONDS
            );

            $acquired = $lock->get();
        } catch (Throwable $exception) {
            Log::warning('ADMS: could not create the provider-config edit lock', [
                'device_id' => $record->getKey(),
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages([
                'data.provider_config' => 'The provider configuration lock could not be created. The device was not saved; try again once the cache is healthy.',
            ]);
        }

        if (! $acquired) {
            throw ValidationException::withMessages([
                'data.provider_config' => 'Another change to this device is in progress. The device was not saved; wait a moment and try again.',
            ]);
        }

        try {
            // Re-read the record under the lock, then re-merge the submitted
            // non-secret fields with the config another writer may have
            // changed since the form was rendered.
            $record->refresh();

            return $save();
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
                // The lock TTL is the backstop.
            }
        }
    }

    /**
     * Merge the submitted non-secret provider fields with the config read
     * under the provider-config lock.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareProviderConfigForUpdate(BiometricDevice $record, array $data): array
    {
        if ($record->providerConfigIsUnreadable() && ! $this->replaceProviderConfig) {
            // The ciphertext became unreadable between form validation and the
            // lock. Preserve it instead of overwriting recoverable material.
            unset($data['provider_config']);
            $this->providerConfigPreserved = true;
            $this->manualWebhookSecretSubmitted = false;
            $this->submittedProviderConfig = null;

            return $data;
        }

        $existingConfig = is_array($record->provider_config) ? $record->provider_config : [];
        $providerType = $data['provider_type'] ?? null;
        $isCloudProvider = filled($providerType) && ! BiometricDevice::providerTypeIsDeviceNative($providerType);

        if ($this->submittedProviderConfig === null && $existingConfig === [] && ! $isCloudProvider) {
            unset($data['provider_config']);

            return $data;
        }

        $merged = array_merge($existingConfig, $this->submittedProviderConfig ?? []);

        if ($isCloudProvider) {
            $tenantKey = Filament::getTenant()?->getKey();
            $tenantId = is_scalar($tenantKey) ? (string) $tenantKey : 'tenant';
            $merged['collection_id'] = 'flux_gym_' . $tenantId;
            $merged['face_list_id'] = 'flux_gym_' . $tenantId;
        }

        // Rotation supersedes any legacy plaintext copy. Only the explicit
        // device-native provider owns `comm_key`; a cloud provider's value is
        // never deleted by an ADMS action, including a cloud→native switch.
        // Provenance for that switch is derived by the model/cast, not here.
        if ($this->updatedCommKey !== null) {
            unset($merged['pushcommkey']);

            if ($record->mayDeleteCommKeyAlias()) {
                if (array_key_exists('comm_key', $merged)) {
                    $this->removedCommKeyAlias = true;
                }

                unset($merged['comm_key']);
            }
        }

        $data['provider_config'] = $merged;

        return $data;
    }

    /**
     * Publish the one-time value only after the outermost transaction commits.
     */
    protected function commitDatabaseTransaction(): void
    {
        parent::commitDatabaseTransaction();

        if (DB::transactionLevel() > 0 && ! (app()->runningUnitTests() && DB::transactionLevel() === 1)) {
            DB::afterCommit(fn () => $this->publishPendingReveal());

            return;
        }

        $this->publishPendingReveal();
    }

    protected function publishPendingReveal(): void
    {
        if ($this->revealPublished) {
            return;
        }

        $this->revealPublished = true;

        /** @var BiometricDevice|null $record */
        $record = $this->record;

        if (! $record instanceof BiometricDevice || ! $record->exists) {
            return;
        }

        if ($this->manualWebhookSecretSubmitted) {
            resolve(WebhookSecretRotationService::class)->auditManualWebhookSecretWrite($record);
        }

        if ($this->providerConfigPreserved) {
            Notification::make()
                ->warning()
                ->title('Provider configuration was not changed')
                ->body('The stored provider configuration is unreadable and was preserved. Use "Replace unreadable provider configuration" on the edit page if you intend to replace it.')
                ->persistent()
                ->send();
        }

        if ($this->updatedCommKey === null || $this->updatedCommKeyHash === null || $this->updatedCommKeySalt === null) {
            return;
        }

        CommKeyReveal::forget($record->id);
        $this->commKeyRevealFailed = ! CommKeyReveal::put($record->id, $this->updatedCommKey);

        resolve(AdmsDeviceAuthenticator::class)->resetFailures($record);

        try {
            activity()
                ->performedOn($record)
                ->withProperties([
                    'gym_id' => $record->gym_id,
                    'action' => 'adms_comm_key_rotated',
                    'removed_comm_key_alias' => $this->removedCommKeyAlias,
                    'provider_type' => $record->provider_type?->value,
                ])
                ->log('ADMS device comm key rotated');
        } catch (Throwable $exception) {
            Log::warning('ADMS: device updated but the comm key audit log failed', [
                'device_id' => $record->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        $notification = Notification::make()
            ->success()
            ->title('Device updated');

        if ($this->updatedCommKey !== null) {
            $notification->body($this->commKeyRevealFailed
                ? 'ADMS Communication Key updated, but it could not be stored for one-time reveal. Rotate it again once the cache is healthy.'
                : 'ADMS Communication Key updated. Use the "Reveal Comm Key" action in the devices table within ' . CommKeyReveal::TTL_MINUTES . ' minutes; it will not be shown again after that.');

            return $notification;
        }

        return $notification->body('The device has been updated successfully.');
    }

    /**
     * Normalize an Eloquent key for the typed lock/reveal helpers.
     */
    private static function deviceKey(Model $record): int|string
    {
        $key = $record->getKey();

        return is_int($key) || is_string($key) ? $key : 0;
    }
}
