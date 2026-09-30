<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\BiometricDeviceStatusEnum;
use App\Models\BiometricDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmsDevice
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config()->boolean('biometrics.adms.enabled', true)) {
            return new Response('ERR: ADMS disabled', 403, ['Content-Type' => 'text/plain']);
        }

        $serial = $request->query('SN');

        if (! is_string($serial) || blank($serial)) {
            return new Response('ERR: Missing SN parameter', 400, ['Content-Type' => 'text/plain']);
        }

        $device = BiometricDevice::findBySerial($serial);

        if (! $device || ! $device->adms_enabled) {
            Log::warning('ADMS: Device authentication failed — unknown or non-ADMS device', [
                'serial_hash' => hash('sha256', $serial),
                'ip' => $request->ip(),
            ]);

            return new Response('ERR: Unknown or inactive device', 401, ['Content-Type' => 'text/plain']);
        }

        if ($device->status === BiometricDeviceStatusEnum::Maintenance) {
            return new Response('ERR: Unknown or inactive device', 401, ['Content-Type' => 'text/plain']);
        }

        // Verify pushcommkey if configured or required by config
        $requireKey = config()->boolean('biometrics.adms.require_comm_key', true);
        $hasKeyConfigured = $device->hasCommKeyConfigured();
        $keyVerified = false;

        $providedKey = $request->query('pushcommkey')
            ?? $request->input('pushcommkey')
            ?? $request->header('pushcommkey')
            ?? $request->header('X-PushCommKey');

        if ($hasKeyConfigured && is_string($providedKey) && filled($providedKey)) {
            $keyVerified = $device->verifyCommKey($providedKey);
        }

        if ($requireKey || $hasKeyConfigured) {
            if (! $keyVerified) {
                Log::warning('ADMS: Device authentication failed — invalid or missing pushcommkey', [
                    'device_id' => $device->id,
                    'serial_hash' => hash('sha256', $serial),
                    'ip' => $request->ip(),
                ]);

                return new Response('ERR: Invalid or missing pushcommkey', 401, ['Content-Type' => 'text/plain']);
            }
        }

        // Auto-recover Inactive device strictly upon verified credential check
        if ($device->status === BiometricDeviceStatusEnum::Inactive) {
            if ($keyVerified) {
                $device->update([
                    'status' => BiometricDeviceStatusEnum::Active,
                    'last_heartbeat_at' => now(),
                ]);
                Cache::forget(BiometricDevice::missCounterCacheKey($device->id));
                Log::info('ADMS: Device auto-recovered to Active status upon verified communication', [
                    'device_id' => $device->id,
                    'serial_hash' => hash('sha256', (string) $device->device_serial),
                ]);
            } else {
                Log::warning('ADMS: Device auto-recovery rejected without verified credential', [
                    'device_id' => $device->id,
                    'serial_hash' => hash('sha256', $serial),
                ]);

                return new Response('ERR: Unknown or inactive device', 401, ['Content-Type' => 'text/plain']);
            }
        } else {
            // Clear miss counter on healthy communication
            Cache::forget(BiometricDevice::missCounterCacheKey($device->id));
        }

        // Bind authenticated device to request
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
