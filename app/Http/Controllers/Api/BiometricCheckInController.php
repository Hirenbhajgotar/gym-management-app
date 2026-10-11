<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\Services\CheckInService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiometricCheckInRequest;
use App\Http\Requests\BiometricCheckOutRequest;
use App\Models\BiometricDevice;
use App\Models\GymMember;
use Illuminate\Http\JsonResponse;

class BiometricCheckInController extends Controller
{
    public function __construct(
        protected CheckInService $checkInService
    ) {}

    public function checkIn(BiometricCheckInRequest $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        // Mode 1: Device-Native (Hardware verified)
        if ($device->isDeviceNative()) {
            $memberIdentifier = $request->validated('gym_member_id')
                ?? $request->validated('member_id')
                ?? $request->validated('user_id')
                ?? $request->validated('pin')
                ?? $request->validated('template_ref');

            if (blank($memberIdentifier)) {
                return response()->json([
                    'error' => 'Invalid payload format',
                    'message' => 'Member identifier is required for device-native verification.',
                ], 422);
            }

            $rawMeta = $request->validated('metadata');
            /** @var array<string, mixed> $metadata */
            $metadata = is_array($rawMeta) ? $rawMeta : [];

            $identifierStr = is_scalar($memberIdentifier) ? (string) $memberIdentifier : '';

            $result = $this->checkInService->processDeviceNativeCheckIn(
                device: $device,
                memberIdentifier: $identifierStr,
                metadata: $metadata
            );

            return response()->json([
                'granted' => $result->granted,
                'result' => $result->result,
                'denial_reason' => $result->denialReason,
                'attendance_id' => $result->attendance?->id,
                'check_in_time' => $result->attendance?->check_in_time?->toISOString(),
            ], $result->granted ? 200 : 403);
        }

        // Mode 2: Cloud AI Provider
        if (! $request->filled('data')) {
            return response()->json([
                'error' => 'Invalid payload format',
                'message' => 'Biometric sample data is required for cloud provider verification.',
            ], 422);
        }

        $rawMeta = $request->validated('metadata');
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($rawMeta) ? $rawMeta : [];
        $rawType = $request->validated('biometric_type');
        $biometricType = is_string($rawType) ? $rawType : 'face';
        $rawData = $request->validated('data');

        $sample = new BiometricSample(
            type: $biometricType,
            data: is_string($rawData) ? $rawData : '',
            metadata: $metadata
        );

        $result = $this->checkInService->processCheckIn($sample, $device);

        return response()->json([
            'granted' => $result->granted,
            'result' => $result->result,
            'denial_reason' => $result->denialReason,
            'attendance_id' => $result->attendance?->id,
            'check_in_time' => $result->attendance?->check_in_time?->toISOString(),
        ], $result->granted ? 200 : 403);
    }

    public function checkOut(BiometricCheckOutRequest $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        $gymMember = GymMember::query()->findOrFail($request->validated('gym_member_id'));

        $result = $this->checkInService->processCheckOut($gymMember, $device);

        return response()->json([
            'granted' => $result->granted,
            'result' => $result->result,
            'denial_reason' => $result->denialReason,
            'attendance_id' => $result->attendance?->id,
            'check_out_time' => $result->attendance?->check_out_time?->toISOString(),
        ], $result->granted ? 200 : 403);
    }
}
