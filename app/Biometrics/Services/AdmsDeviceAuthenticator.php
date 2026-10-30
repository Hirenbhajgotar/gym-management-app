<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\ValueObjects\AdmsAuthenticationFailureReason;
use App\Biometrics\ValueObjects\AdmsAuthenticationResult;
use App\Enums\BiometricDeviceStatusEnum;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single authentication path for every ADMS request.
 *
 * The middleware is the production entry point; the controller consumes the
 * device this resolver binds instead of re-implementing key verification.
 *
 * The per-device lockout is enforced before the key is verified. That is the
 * only ordering that caps guesses: a locked-out terminal cannot evaluate even a
 * correct key, so an attacker who knows the serial can deny service until the
 * cooldown expires or an operator runs `biometrics:reset-adms-lockout`. The
 * tradeoff is deliberate and documented in ADR-0005 (NEG-002).
 */
class AdmsDeviceAuthenticator
{
    public const FAILURE_COUNTER_PREFIX = 'biometric_device:auth_failures:';

    public const LOCKOUT_PREFIX = 'biometric_device:auth_lockout:';

    public function authenticateRequest(Request $request): AdmsAuthenticationResult
    {
        $serial = $request->query('SN');

        if (! is_string($serial) || blank($serial)) {
            return AdmsAuthenticationResult::failure(AdmsAuthenticationFailureReason::MissingSerial);
        }

        $device = $this->resolveDevice($serial);

        if (! $device) {
            $this->performDummyVerification($request);
            $this->logFailure(AdmsAuthenticationFailureReason::UnknownOrDisabledDevice, $serial, $request);

            return AdmsAuthenticationResult::failure(AdmsAuthenticationFailureReason::UnknownOrDisabledDevice);
        }

        if ($device->status === BiometricDeviceStatusEnum::Maintenance) {
            $this->logFailure(AdmsAuthenticationFailureReason::Maintenance, $serial, $request, $device);

            return AdmsAuthenticationResult::failure(AdmsAuthenticationFailureReason::Maintenance, $device);
        }

        $requireKey = config()->boolean('biometrics.adms.require_comm_key', true);
        $hasKeyConfigured = $device->hasCommKeyConfigured();

        if (! $requireKey && ! $hasKeyConfigured) {
            $this->resetFailures($device);

            return AdmsAuthenticationResult::success($device, false);
        }

        $providedKey = $this->providedKey($request);

        // Lockout first: every request that reaches PBKDF2 is a guess. A
        // correct key is deliberately not exempt while the lockout is active;
        // otherwise an attacker with unlimited guesses always gets a free
        // verification and the lockout caps nothing.
        if ($hasKeyConfigured && $this->isLockedOut($device)) {
            Log::warning('ADMS: device authentication rejected — temporary lockout active', [
                'device_id' => $device->id,
                'serial_hash' => hash('sha256', $serial),
                'ip' => $request->ip(),
            ]);

            return AdmsAuthenticationResult::failure(AdmsAuthenticationFailureReason::LockedOut, $device);
        }

        // Spend PBKDF2 work even when a keyed device receives no key, otherwise
        // response timing still distinguishes "serial exists and is keyed" from
        // an invalid key.
        if ($hasKeyConfigured && (! is_string($providedKey) || blank($providedKey))) {
            $this->performDummyVerification($request);
        }

        $keyVerified = $hasKeyConfigured
            && is_string($providedKey)
            && filled($providedKey)
            && $device->verifyCommKey($providedKey);

        if ($keyVerified) {
            $this->resetFailures($device);

            return AdmsAuthenticationResult::success($device, true);
        }

        if ($hasKeyConfigured) {
            $this->recordFailure($device, $request);
        }

        return AdmsAuthenticationResult::failure(AdmsAuthenticationFailureReason::InvalidOrMissingCommKey, $device);
    }

    public function isLockedOut(BiometricDevice $device): bool
    {
        try {
            return Cache::has(self::LOCKOUT_PREFIX . $this->deviceCacheKey($device));
        } catch (Throwable $exception) {
            Log::critical('ADMS: cache unavailable while checking the device lockout; continuing without lockout state', [
                'device_id' => $device->id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * Clear the failure counter and lockout for a device.
     *
     * Called on successful authentication, key rotation, and re-provisioning so a
     * corrected credential is usable immediately instead of after the cooldown.
     */
    public function resetFailures(BiometricDevice $device): bool
    {
        try {
            Cache::forget(self::FAILURE_COUNTER_PREFIX . $this->deviceCacheKey($device));
            Cache::forget(self::LOCKOUT_PREFIX . $this->deviceCacheKey($device));

            return true;
        } catch (Throwable $exception) {
            Log::critical('ADMS: cache unavailable while clearing the device lockout', [
                'device_id' => $device->id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    private function deviceCacheKey(BiometricDevice $device): string
    {
        $key = $device->getKey();

        return is_int($key) || is_string($key) ? (string) $key : '';
    }

    private function resolveDevice(string $serial): ?BiometricDevice
    {
        $device = BiometricDevice::findBySerial($serial);

        if (! $device || ! $device->adms_enabled) {
            return null;
        }

        return $device;
    }

    private function providedKey(Request $request): ?string
    {
        $candidates = [
            $request->query('pushcommkey'),
            $request->input('pushcommkey'),
            $request->header('pushcommkey'),
            $request->header('X-PushCommKey'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && filled($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Spend the same PBKDF2 work for an unknown serial as a keyed device would,
     * so response timing cannot be used to enumerate valid serials.
     */
    private function performDummyVerification(Request $request): void
    {
        $candidate = $this->providedKey($request) ?? '000000';

        BiometricDevice::performDummyCommKeyVerification($candidate);
    }

    private function recordFailure(BiometricDevice $device, Request $request): void
    {
        $maxFailures = max(1, config()->integer('biometrics.adms.auth_max_failures', 5));
        $lockoutSeconds = max(1, config()->integer('biometrics.adms.auth_lockout_seconds', 900));
        $counterKey = self::FAILURE_COUNTER_PREFIX . $this->deviceCacheKey($device);

        try {
            Cache::add($counterKey, 0, now()->addSeconds($lockoutSeconds));
            $attempts = Cache::increment($counterKey);
        } catch (Throwable $exception) {
            Log::critical('ADMS: cache unavailable while recording a failed authentication; guessing control is disabled for this request', [
                'device_id' => $device->id,
                'exception' => $exception::class,
            ]);

            return;
        }

        if (! is_int($attempts) || $attempts < 1) {
            Log::critical('ADMS: cache increment failed while recording a failed authentication; failing closed', [
                'device_id' => $device->id,
            ]);
            $attempts = $maxFailures;
        }

        Log::warning('ADMS: Device authentication failed — invalid or missing pushcommkey', [
            'device_id' => $device->id,
            'serial_hash' => hash('sha256', (string) $device->device_serial),
            'ip' => $request->ip(),
            'failed_attempts' => $attempts,
        ]);

        if ($attempts < $maxFailures) {
            return;
        }

        try {
            Cache::put(self::LOCKOUT_PREFIX . $this->deviceCacheKey($device), true, now()->addSeconds($lockoutSeconds));
            Cache::forget($counterKey);
        } catch (Throwable $exception) {
            Log::critical('ADMS: cache unavailable while setting the device lockout', [
                'device_id' => $device->id,
                'exception' => $exception::class,
            ]);

            return;
        }

        Log::warning('ADMS: device temporarily locked out after repeated failed comm key attempts', [
            'device_id' => $device->id,
            'serial_hash' => hash('sha256', (string) $device->device_serial),
            'lockout_seconds' => $lockoutSeconds,
        ]);
    }

    private function logFailure(
        AdmsAuthenticationFailureReason $reason,
        string $serial,
        Request $request,
        ?BiometricDevice $device = null,
    ): void {
        Log::warning('ADMS: Device authentication failed', [
            'reason' => $reason->value,
            'device_id' => $device?->id,
            'serial_hash' => hash('sha256', $serial),
            'ip' => $request->ip(),
        ]);
    }
}
