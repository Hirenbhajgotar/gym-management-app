<?php

namespace App\Http\Controllers\Api;

use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\Services\CheckInService;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BiometricWebhookController extends Controller
{
    public function __construct(
        protected CheckInService $checkInService
    ) {}

    public function handle(Request $request, string $token): JsonResponse
    {
        // Verify webhook token
        $device = BiometricDevice::whereNotNull('device_token')
            ->get()
            ->first(function ($d) use ($token) {
                return hash_equals($d->device_token, $token);
            });

        if (! $device) {
            return response()->json(['error' => 'Invalid webhook token'], 401);
        }

        // Verify HMAC signature if provided
        $signature = $request->header('X-Signature') ?? $request->header('X-Hub-Signature-256');

        if ($signature && ! $this->verifySignature($request, $signature, $device)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();

        try {
            // Transform vendor payload to BiometricSample
            $sample = $this->transformPayload($payload, $device);

            if (! $sample) {
                return response()->json(['error' => 'Invalid payload format'], 422);
            }

            // Process check-in
            $result = $this->checkInService->processCheckIn($sample, $device);

            return response()->json([
                'success' => $result->granted,
                'result' => $result->result,
                'denial_reason' => $result->denialReason,
            ], $result->granted ? 200 : 403);
        } catch (\Exception $e) {
            Log::error('Biometric webhook error', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    protected function transformPayload(array $payload, BiometricDevice $device): ?BiometricSample
    {
        // Support ZKTeco format
        if (isset($payload['SN']) && isset($payload['photo'])) {
            return new BiometricSample(
                type: 'face',
                data: $payload['photo'],
                metadata: ['device_sn' => $payload['SN']]
            );
        }

        // Support Suprema BioStar format
        if (isset($payload['device_id']) && isset($payload['image'])) {
            return new BiometricSample(
                type: $payload['biometric_type'] ?? 'face',
                data: $payload['image'],
                metadata: $payload
            );
        }

        // Support generic format
        if (isset($payload['data']) && isset($payload['type'])) {
            return new BiometricSample(
                type: $payload['type'],
                data: $payload['data'],
                metadata: $payload['metadata'] ?? []
            );
        }

        return null;
    }

    protected function verifySignature(Request $request, string $signature, BiometricDevice $device): bool
    {
        $secret = $device->provider_config['webhook_secret'] ?? '';

        if (empty($secret)) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
