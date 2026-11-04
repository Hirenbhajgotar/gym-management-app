<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Biometrics\Services\AdmsDeviceAuthenticator;
use App\Biometrics\ValueObjects\AdmsAuthenticationFailureReason;
use App\Enums\BiometricDeviceStatusEnum;
use App\Models\BiometricDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmsDevice
{
    private const UNAUTHORIZED_BODY = 'ERR: Unauthorized';

    public function __construct(private readonly AdmsDeviceAuthenticator $authenticator) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config()->boolean('biometrics.adms.enabled', true)) {
            return new Response('ERR: ADMS disabled', 403, ['Content-Type' => 'text/plain']);
        }

        $result = $this->authenticator->authenticateRequest($request);

        if (! $result->authenticated) {
            $status = $result->failureReason === AdmsAuthenticationFailureReason::MissingSerial ? 400 : 401;

            return new Response(self::UNAUTHORIZED_BODY, $status, ['Content-Type' => 'text/plain']);
        }

        /** @var BiometricDevice $device */
        $device = $result->device;

        if ($device->status === BiometricDeviceStatusEnum::Inactive) {
            $unkeyedRecovery = ! $result->keyVerified
                && ! $device->hasCommKeyConfigured()
                && ! config()->boolean('biometrics.adms.require_comm_key', false);

            if (! $result->keyVerified && ! $unkeyedRecovery) {
                Log::warning('ADMS: Device auto-recovery rejected without verified credential', [
                    'device_id' => $device->id,
                    'serial_hash' => hash('sha256', (string) $device->device_serial),
                ]);

                return new Response(self::UNAUTHORIZED_BODY, 401, ['Content-Type' => 'text/plain']);
            }

            $device->update([
                'status' => BiometricDeviceStatusEnum::Active,
                'last_heartbeat_at' => now(),
            ]);
            BiometricDevice::clearMissCounter($device->id);
            Log::info('ADMS: Device auto-recovered to Active status upon verified communication', [
                'device_id' => $device->id,
                'serial_hash' => hash('sha256', (string) $device->device_serial),
            ]);
        } else {
            BiometricDevice::clearMissCounter($device->id);
        }

        $request->attributes->set('adms_device', $device);

        return $next($request);
    }
}
