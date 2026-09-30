<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\BiometricDeviceStatusEnum;
use App\Models\BiometricDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBiometricDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing or invalid Authorization header',
            ], 401);
        }

        $plainToken = substr($authHeader, 7);

        if (blank($plainToken)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Empty token',
            ], 401);
        }

        // O(1) device lookup via indexed token_hash and Redis cache
        $device = BiometricDevice::findByPlainToken($plainToken);

        if (! $device) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid device token',
            ], 401);
        }

        // Bind device to request for use in controllers
        $request->attributes->set('device', $device);

        if (isset($device->is_enabled) && ! $device->is_enabled) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Biometric device has been administratively disabled',
            ], 403);
        }

        // Allow heartbeat endpoint to reactivate inactive devices; reject punch/sync if device is inactive
        if ($device->status !== BiometricDeviceStatusEnum::Active && ! $request->is('*heartbeat*')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Biometric device is inactive',
            ], 403);
        }

        return $next($request);
    }
}
