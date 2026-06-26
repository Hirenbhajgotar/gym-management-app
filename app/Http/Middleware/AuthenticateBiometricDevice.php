<?php

namespace App\Http\Middleware;

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

        // Find active device with matching token
        $devices = BiometricDevice::query()->active()->get();

        $device = null;
        foreach ($devices as $d) {
            if ($d->verifyToken($plainToken)) {
                $device = $d;
                break;
            }
        }

        if (! $device) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid device token',
            ], 401);
        }

        // Bind device to request for use in controllers
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
