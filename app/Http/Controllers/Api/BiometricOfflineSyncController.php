<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\Services\OfflineSyncService;
use App\Http\Controllers\Controller;
use App\Http\Requests\OfflineSyncRequest;
use App\Models\BiometricDevice;
use Illuminate\Http\JsonResponse;

class BiometricOfflineSyncController extends Controller
{
    public function __construct(
        protected OfflineSyncService $offlineSyncService
    ) {}

    public function sync(OfflineSyncRequest $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        $rawEvents = $request->validated('events');
        /** @var array<int, array<string, mixed>> $events */
        $events = is_array($rawEvents) ? $rawEvents : [];

        $results = $this->offlineSyncService->processBatch($events, $device);

        $accepted = collect($results)->where('status', 'accepted')->count();
        $denied = collect($results)->where('status', 'retroactively_denied')->count();
        $requiresReview = collect($results)->where('status', 'requires_review')->count();

        return response()->json([
            'success' => true,
            'total_events' => count($events),
            'accepted' => $accepted,
            'requires_review' => $requiresReview,
            'retroactively_denied' => $denied,
            'results' => $results,
        ]);
    }
}
