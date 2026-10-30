<?php

declare(strict_types=1);

namespace App\Casts;

use App\Exceptions\ProviderProvenanceException;
use App\Models\BiometricDevice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Encrypted provider config that refuses client-supplied provenance.
 *
 * `comm_key_source` records that a `comm_key` survived a cloud→native switch
 * and is still a provider credential, not an ADMS alias. The model's saving
 * hook derives it server-side, but direct Eloquent writers (`update()`,
 * `saveQuietly()`) can submit arbitrary `provider_config` arrays. This cast
 * strips the marker from every write except:
 *
 * - a genuine cloud→native transition (where the saving hook derives it), or
 * - a re-write of a marker that is already persisted for the row, including a
 *   null ↔ device_native rewording of the same provider policy.
 *
 * A write that omits the marker while re-submitting the persisted cloud
 * credential keeps the marker. Replacing that credential without an explicit
 * provider transition is refused instead, because losing the marker would
 * expose it to ADMS cleanup.
 *
 * Wire formats that bypass attribute casts (`Model::query()->update()`, raw
 * `DB::table()` writes, direct ciphertext imports) cannot be intercepted here.
 */
final class ProviderConfigCast extends SafeEncryptedArray
{
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_array($value)) {
            // `saveQuietly()`/`updateQuietly()` bypass the saving hook, so the
            // cast must derive the same provenance for a genuine cloud→native
            // transition. Otherwise a quiet switch could leave a cloud
            // credential looking like an ADMS alias.
            $newIsNative = BiometricDevice::providerTypeIsDeviceNative($model->getAttribute('provider_type'));
            $originalIsNative = BiometricDevice::providerTypeIsDeviceNative($model->getOriginal('provider_type'));

            if ($model->isDirty('provider_type')
                && $newIsNative
                && ! $originalIsNative
                && array_key_exists('comm_key', $value)) {
                $value['comm_key_source'] = 'cloud';
            }

            // Omitting the marker on an unrelated rewrite must not erase
            // persisted provenance: a cloud credential would then look like an
            // ADMS alias and become deletable by a rotation or cleanup.
            if (array_key_exists('comm_key', $value)
                && ! array_key_exists('comm_key_source', $value)
                && $newIsNative === $originalIsNative) {
                $persisted = $this->persistedConfig($model);

                if (($persisted['comm_key_source'] ?? null) === 'cloud') {
                    if (($persisted['comm_key'] ?? null) === $value['comm_key']) {
                        $value['comm_key_source'] = 'cloud';
                    } else {
                        throw ProviderProvenanceException::whileReplacingCloudCredential();
                    }
                }
            }

            if (array_key_exists('comm_key_source', $value)
                && ($value['comm_key_source'] !== 'cloud' || ! $this->mayKeepProvenance($model))) {
                unset($value['comm_key_source']);
            }
        }

        return parent::set($model, $key, $value, $attributes);
    }

    private function mayKeepProvenance(Model $model): bool
    {
        $newIsNative = BiometricDevice::providerTypeIsDeviceNative($model->getAttribute('provider_type'));
        $originalIsNative = BiometricDevice::providerTypeIsDeviceNative($model->getOriginal('provider_type'));

        if ($model->isDirty('provider_type') && $newIsNative !== $originalIsNative) {
            return $newIsNative && ! $originalIsNative;
        }

        // A rewording of the same policy (null ↔ device_native) or an
        // unrelated write may re-submit the marker that is already persisted.
        // A stored marker is server-derived provenance, not a forgery.
        return $this->persistedConfigHasCloudMarker($model);
    }

    private function persistedConfigHasCloudMarker(Model $model): bool
    {
        return ($this->persistedConfig($model)['comm_key_source'] ?? null) === 'cloud';
    }

    /**
     * The decrypted config already stored for the row, if it is readable.
     *
     * `getRawOriginal()` is used so a pending in-memory rewrite cannot vouch
     * for itself. A rotated APP_KEY or truncated ciphertext yields null, which
     * callers treat as "no persisted provenance".
     *
     * @return array<array-key, mixed>|null
     */
    private function persistedConfig(Model $model): ?array
    {
        $raw = $model->getRawOriginal('provider_config');

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($raw), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
