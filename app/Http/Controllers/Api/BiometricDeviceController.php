<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\BiometricProviderRegistry;
use App\Enums\BiometricDeviceStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BiometricDeviceController extends Controller
{
    public function __construct(
        protected BiometricProviderRegistry $registry
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        if (isset($device->is_enabled) && ! $device->is_enabled) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Biometric device has been administratively disabled',
            ], 403);
        }

        $device->last_heartbeat_at = now();
        if ($device->status === BiometricDeviceStatusEnum::Inactive) {
            $device->status = BiometricDeviceStatusEnum::Active;
        }
        $device->save();
        Cache::forget(BiometricDevice::missCounterCacheKey($device->id));

        return response()->json([
            'success' => true,
            'status' => $device->status->value,
            'last_heartbeat_at' => $device->last_heartbeat_at->toISOString(),
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        $supportedTypes = [$device->device_type ?? 'face'];

        if ($device->hasCloudProvider() && $device->provider_type) {
            $provider = $this->registry->resolve($device->provider_type, is_array($device->provider_config) ? $device->provider_config : []);
            $supportedTypes = $provider->getSupportedTypes();
        }

        $heartbeatInterval = config()->integer('biometrics.heartbeat_interval_seconds', 60);

        return response()->json([
            'device_id' => $device->id,
            'device_name' => $device->device_name,
            'device_type' => $device->device_type,
            'provider_type' => $device->provider_type?->value,
            'supported_types' => $supportedTypes,
            'purpose' => $device->purpose->value,
            'adms_enabled' => $device->adms_enabled,
            'heartbeat_interval_seconds' => $heartbeatInterval,
            'liveness_threshold' => config()->float('biometrics.liveness_threshold', 0.75),
            'confidence_threshold' => config()->float('biometrics.confidence_threshold', 0.80),
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        if ($device->isDeviceNative() || ! $device->provider_type) {
            return response()->json([
                'success' => true,
                'message' => 'Device is operating in Device-Native mode (Hardware matches templates on-chip)',
            ], 200);
        }

        try {
            $provider = $this->registry->resolve($device->provider_type, is_array($device->provider_config) ? $device->provider_config : []);
            $isAvailable = $provider->isAvailable();

            return response()->json([
                'success' => $isAvailable,
                'message' => $isAvailable
                    ? 'Connection successful'
                    : 'Provider not available',
            ], $isAvailable ? 200 : 503);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 503);
        }
    }
}
