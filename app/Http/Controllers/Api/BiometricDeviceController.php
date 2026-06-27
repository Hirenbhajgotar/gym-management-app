<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\BiometricProviderRegistry;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiometricDeviceController extends Controller
{
    public function __construct(
        protected BiometricProviderRegistry $registry
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        $device->last_heartbeat_at = now();
        $device->save();

        return response()->json([
            'success' => true,
            'last_heartbeat_at' => $device->last_heartbeat_at->toISOString(),
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        $provider = $this->registry->resolve($device->provider_type, is_array($device->provider_config) ? $device->provider_config : []);

        // Return masked config
        $configStr = $device->provider_config ?? '{}';
        $config = is_array($configStr) ? $configStr : json_decode($configStr, true) ?? [];
        $maskedConfig = [];

        foreach ($config as $key => $value) {
            if (str_contains(strtolower($key), 'secret') || str_contains(strtolower($key), 'key') || str_contains(strtolower($key), 'password')) {
                $maskedConfig[$key] = '***';
            } else {
                $maskedConfig[$key] = $value;
            }
        }

        return response()->json([
            'device_id' => $device->id,
            'device_name' => $device->device_name,
            'device_type' => $device->device_type,
            'provider_type' => $device->provider_type,
            'supported_types' => $provider->getSupportedTypes(),
            'offline_capable' => $device->offline_capable,
            'supports_multi_modal' => $device->supports_multi_modal,
            'config' => $maskedConfig,
            'liveness_threshold' => config('biometrics.liveness_threshold', 0.75),
            'confidence_threshold' => config('biometrics.confidence_threshold', 0.80),
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        try {
            $provider = $this->registry->resolve($device->provider_type, is_array($device->provider_config) ? $device->provider_config : []);
            $isAvailable = $provider->isAvailable();

            return response()->json([
                'success' => $isAvailable,
                'message' => $isAvailable
                    ? 'Connection successful'
                    : 'Provider not available',
            ], $isAvailable ? 200 : 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 503);
        }
    }
}
