<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\CheckInResult;
use App\Biometrics\Services\BiometricLogRedactor;
use App\Biometrics\Services\CheckInService;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\GymMember;
use App\Models\User;
use Exception;
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
        // O(1) device lookup via indexed token_hash and Redis cache
        $device = BiometricDevice::findByPlainToken($token);

        if (! $device || ! $device->isActive()) {
            return response()->json(['error' => 'Invalid or inactive webhook token'], 401);
        }

        // Verify HMAC signature if provided
        // Support custom header name from device config, default to X-Signature
        $signatureHeader = $device->provider_config['webhook_signature_header'] ?? 'X-Signature';
        $signature = $request->header($signatureHeader) ?? $request->header('X-Hub-Signature-256');

        // If device has webhook_secret configured, signature is required
        $hasWebhookSecret = filled($device->provider_config['webhook_secret'] ?? '');

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

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        try {
            // Mode 1: Device-Native (Hardware does verification, no cloud provider configured)
            if ($device->isDeviceNative()) {
                $memberIdentifier = $this->extractMemberIdentifier($payload);

                if (blank($memberIdentifier)) {
                    Log::warning('BiometricWebhook: Member identifier missing in device-native payload', [
                        'device_id' => $device->id,
                        'payload' => $this->sanitizePayload($payload),
                    ]);

                    return response()->json([
                        'error' => 'Invalid payload format',
                        'message' => 'Member identifier (member_id, user_id, pin, etc.) is required for device-native verification.',
                    ], 422);
                }

                $identifierStr = is_scalar($memberIdentifier) ? (string) $memberIdentifier : '';
                $result = $this->checkInService->processDeviceNativeCheckIn($device, $identifierStr, $payload);

                return $this->formatResponse($result, $device);
            }

            // Mode 2: Cloud AI Provider (AWS Rekognition, Azure Face, BioID, etc.)
            $sample = $this->transformPayload($payload, $device);

            if (! $sample) {
                return response()->json(['error' => 'Invalid payload format'], 422);
            }

            $result = $this->checkInService->processCheckIn($sample, $device);

            return $this->formatResponse($result, $device);
        } catch (Exception $e) {
            Log::error('Biometric webhook error', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
                'payload' => $this->sanitizePayload($payload),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractMemberIdentifier(array $payload): mixed
    {
        $eventId = (isset($payload['event']) && is_array($payload['event'])) ? ($payload['event']['user_id'] ?? null) : null;
        $userInfoId = (isset($payload['userInfo']) && is_array($payload['userInfo'])) ? ($payload['userInfo']['user_id'] ?? null) : null;

        return $payload['gym_member_id']
            ?? $payload['member_id']
            ?? $payload['user_id']
            ?? $payload['pin']
            ?? $payload['employee_no']
            ?? $payload['card_no']
            ?? $payload['card_number']
            ?? $payload['template_ref']
            ?? $eventId
            ?? $userInfoId
            ?? $payload['id']
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function transformPayload(array $payload, BiometricDevice $device): ?BiometricSample
    {
        // Support ZKTeco format
        if (isset($payload['SN']) && isset($payload['photo']) && is_scalar($payload['photo'])) {
            $sn = is_scalar($payload['SN']) ? (string) $payload['SN'] : '';

            return new BiometricSample(
                type: 'face',
                data: (string) $payload['photo'],
                metadata: ['device_sn' => $sn]
            );
        }

        // Support Suprema BioStar format
        if (isset($payload['device_id']) && isset($payload['image']) && is_scalar($payload['image'])) {
            $bioType = is_scalar($payload['biometric_type'] ?? null) ? (string) $payload['biometric_type'] : 'face';

            return new BiometricSample(
                type: $bioType,
                data: (string) $payload['image'],
                metadata: $payload
            );
        }

        // Support generic format
        if (isset($payload['data']) && isset($payload['type']) && is_scalar($payload['data']) && is_scalar($payload['type'])) {
            $rawMeta = $payload['metadata'] ?? [];
            /** @var array<string, mixed> $meta */
            $meta = is_array($rawMeta) ? $rawMeta : [];

            return new BiometricSample(
                type: (string) $payload['type'],
                data: (string) $payload['data'],
                metadata: $meta
            );
        }

        return null;
    }

    protected function verifySignature(Request $request, string $signature, BiometricDevice $device): bool
    {
        $rawSecret = is_array($device->provider_config) ? ($device->provider_config['webhook_secret'] ?? '') : '';
        $secret = is_scalar($rawSecret) ? (string) $rawSecret : '';

        // If no secret is configured, reject the request for security
        // This prevents processing unverified webhooks
        if (blank($secret)) {
            Log::warning('BiometricWebhook: Webhook secret not configured for device', [
                'device_id' => $device->id,
                'device_name' => $device->device_name,
                'ip' => $request->ip(),
            ]);

            return false;
        }

        $payload = $request->getContent();
        $rawSignature = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $rawSignature);
    }

    /**
     * Format the webhook response with door control information
     */
    protected function formatResponse(CheckInResult $result, BiometricDevice $device): JsonResponse
    {
        if (! $result->granted) {
            $status = $result->denialReason === 'anti_passback_rejection' ? 429 : 403;

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
            ], $status);
        }

        $doorAction = 'none';
        $doorFailureReason = null;

        if ($result->doorResult && $device->isDoorAccess()) {
            $doorAction = match ($result->doorResult->status) {
                // skipped = relay/wiegand — tell device to trigger relay
                'skipped' => 'unlock',
                // unlocked = API already unlocked it
                'unlocked' => 'confirm_unlock',
                // locked/failed = door control denied or API error
                'locked', 'failed' => 'keep_locked',
                // not_applicable = attendance_only device, no door action
                'not_applicable' => 'none',
                default => 'none',
            };

            if (in_array($result->doorResult->status, ['locked', 'failed'], true)) {
                $doorFailureReason = $result->doorResult->failureReason;
            }
        }

        $attendanceable = $result->attendance?->attendanceable;
        $memberName = $attendanceable instanceof GymMember ? $attendanceable->name : ($attendanceable instanceof User ? $attendanceable->name : 'Member');
        $durationSeconds = config()->integer('biometrics.default_unlock_duration', 5);

        $doorPayload = [
            'action' => $doorAction,
            'duration_seconds' => $durationSeconds,
        ];
        if ($doorFailureReason !== null) {
            $doorPayload['failure_reason'] = $doorFailureReason;
        }

        $displayMessage = 'Welcome, ' . ($memberName ?: 'Member') . '!';
        $beepPattern = 'success';
        $ledColor = 'green';

        if ($doorAction === 'keep_locked') {
            $displayMessage = 'Checked in, but door failed to unlock. Please see staff.';
            $beepPattern = 'warning';
            $ledColor = 'yellow';
        }

        return response()->json([
            'granted' => true,
            'member_name' => $memberName,

            'door' => $doorPayload,

            'display' => [
                'message' => $displayMessage,
                'beep_pattern' => $beepPattern,
                'led_color' => $ledColor,
            ],
        ], 200);
    }

    /**
     * Get denial message for display on device
     */
    protected function getDenialMessage(?string $reason): string
    {
        return match ($reason) {
            'anti_passback', 'anti_passback_rejection' => 'Anti-passback: duplicate punch detected. Please wait.',
            'membership_expired' => 'Membership expired. Please contact reception.',
            'payment_pending' => 'Payment pending. Please pay at reception.',
            'membership_frozen' => 'Membership is on hold.',
            'no_biometric_consent' => 'Please complete registration at reception.',
            'verification_failed' => 'Biometric not recognized. Please try again.',
            'liveness_failed' => 'Liveness check failed. Please look at camera.',
            'door_controller_unreachable' => 'Door system error. Please contact staff.',
            'no_active_check_in' => 'No active check-in found.',
            'already_checked_in' => 'Already checked in.',
            'device_offline' => 'Device offline. Please try again later.',
            'untrusted_door_controller_url' => 'Door system configuration error. Please contact staff.',
            default => 'Access denied. Please contact reception.',
        };
    }

    /**
     * Sanitize payload for logging by redacting sensitive and raw biometric fields
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $payload): array
    {
        /** @var array<string, mixed> */
        return BiometricLogRedactor::redact($payload);
    }
}
