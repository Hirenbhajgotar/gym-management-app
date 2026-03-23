<?php

namespace App\Http\Controllers\Api;

use App\Biometrics\Services\OfflineSyncService;
use App\Http\Controllers\Controller;
use App\Http\Requests\OfflineSyncRequest;
use Illuminate\Http\JsonResponse;

class BiometricOfflineSyncController extends Controller
{
    public function __construct(
        protected OfflineSyncService $offlineSyncService
    ) {}

    public function sync(OfflineSyncRequest $request): JsonResponse
    {
        /** @var \App\Models\BiometricDevice $device */
        $device = $request->attributes->get('device');

        $events = $request->validated('events');

        $results = $this->offlineSyncService->processBatch($events, $device);

        $accepted = collect($results)->where('status', 'accepted')->count();
        $denied = collect($results)->where('status', 'retroactively_denied')->count();

        return response()->json([
            'success' => true,
            'total_events' => count($events),
            'accepted' => $accepted,
            'retroactively_denied' => $denied,
            'results' => $results,
        ]);
    }
}
