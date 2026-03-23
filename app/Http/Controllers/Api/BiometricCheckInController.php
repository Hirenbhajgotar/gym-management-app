<?php

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

        $sample = new BiometricSample(
            type: $request->validated('biometric_type'),
            data: $request->validated('data'),
            metadata: $request->validated('metadata', [])
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

        $gymMember = GymMember::findOrFail($request->validated('gym_member_id'));

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
