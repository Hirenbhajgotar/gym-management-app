<?php

namespace App\Http\Controllers\Api;

use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\Services\CheckInService;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
                return Hash::check($token, $d->device_token);
            });

        if (! $device) {
            return response()->json(['error' => 'Invalid webhook token'], 401);
        }

        // Verify HMAC signature if provided
        // Support custom header name from device config, default to X-Signature
        $signatureHeader = $device->provider_config['webhook_signature_header'] ?? 'X-Signature';
        $signature = $request->header($signatureHeader) ?? $request->header('X-Hub-Signature-256');

        // If device has webhook_secret configured, signature is required
        $hasWebhookSecret = ! empty($device->provider_config['webhook_secret'] ?? '');

        // If signature is required but not provided, reject
        if ($hasWebhookSecret && ! $signature) {
            Log::warning('BiometricWebhook: Signature required but not provided', [
                'device_id' => $device->id,
                'device_name' => $device->device_name,
                'expected_header' => $signatureHeader,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Signature required'], 401);
        }

        // If signature is provided but invalid
        if ($signature && ! $this->verifySignature($request, $signature, $device)) {
            Log::warning('BiometricWebhook: Invalid signature', [
                'device_id' => $device->id,
                'device_name' => $device->device_name,
                'ip' => $request->ip(),
            ]);

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

            return $this->formatResponse($result, $device);
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

        // If no secret is configured, reject the request for security
        // This prevents processing unverified webhooks
        if (empty($secret)) {
            Log::warning('BiometricWebhook: Webhook secret not configured for device', [
                'device_id' => $device->id,
                'device_name' => $device->device_name,
                'ip' => $request->ip(),
            ]);

            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Format the webhook response with door control information
     */
    protected function formatResponse($result, BiometricDevice $device): JsonResponse
    {
        if (! $result->granted) {
            return response()->json([
                'granted' => false,
                'reason' => $result->denialReason,

                // Door stays locked
                'door' => [
                    'action' => 'keep_locked',
                ],

                // Device display
                'display' => [
                    'message' => $this->getDenialMessage($result->denialReason),
                    'beep_pattern' => 'error',
                    'led_color' => 'red',
                ],
            ], 403);
        }

        $doorAction = 'none';

        if ($result->doorResult && $device->isDoorAccess()) {
            $doorAction = match ($result->doorResult->status) {
                // skipped = relay/wiegand — tell device to trigger relay
                'skipped' => 'unlock',
                // unlocked = API already unlocked it
                'unlocked' => 'confirm_unlock',
                // not_applicable = attendance_only device, no door action
                'not_applicable' => 'none',
                default => 'none',
            };
        }

        return response()->json([
            'granted' => true,
            'member_name' => $result->attendance?->attendanceable?->name,

            'door' => [
                'action' => $doorAction,
                // unlock          → device must trigger relay/wiegand
                // confirm_unlock  → API already did it, device just shows green
                // none            → attendance only device, no door action
                'duration_seconds' => $device->unlock_duration_seconds,
                'zone' => $device->door_zone,
            ],

            'display' => [
                'message' => 'Welcome, ' . ($result->attendance?->attendanceable?->name ?? 'Member') . '!',
                'beep_pattern' => 'success',
                'led_color' => 'green',
            ],
        ], 200);
    }

    /**
     * Get denial message for display on device
     */
    protected function getDenialMessage(?string $reason): string
    {
        return match ($reason) {
            'membership_expired' => 'Membership expired. Please contact reception.',
            'payment_pending' => 'Payment pending. Please pay at reception.',
            'membership_frozen' => 'Membership is on hold.',
            'no_biometric_consent' => 'Please complete registration at reception.',
            'verification_failed' => 'Biometric not recognized. Please try again.',
            'liveness_failed' => 'Liveness check failed. Please look at camera.',
            'door_controller_unreachable' => 'Door system error. Please contact staff.',
            default => 'Access denied. Please contact reception.',
        };
    }
}
