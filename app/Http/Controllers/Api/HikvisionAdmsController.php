<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\Services\AdmsEventService;
use App\Biometrics\Services\BiometricReceiptService;
use App\Biometrics\Services\BiometricTimestampParser;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricReceiptStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hikvision ISAPI event push handler.
 *
 * Handles XML EventNotificationAlert payloads from Hikvision
 * face recognition terminals and access control devices.
 */
class HikvisionAdmsController extends Controller
{
    public function __construct(
        protected AdmsEventService $admsEventService,
        protected BiometricReceiptService $receiptService
    ) {}

    /**
     * Handle Hikvision ISAPI event push.
     *
     * POST /api/v1/biometric/hikvision/event
     * Authenticated via existing AuthenticateBiometricDevice middleware.
     */
    public function handleEvent(Request $request): JsonResponse
    {
        if (! config('biometrics.adms.enabled', true)) {
            return response()->json(['error' => 'ADMS disabled'], 403);
        }

        /** @var BiometricDevice|null $device */
        $device = $request->attributes->get('device');

        if (! $device) {
            return response()->json(['error' => 'Device not authenticated'], 401);
        }

        if ($device->status === BiometricDeviceStatusEnum::Maintenance) {
            return response()->json(['error' => 'Device in maintenance mode'], 401);
        }

        // Auto-recover Inactive device on authenticated communication
        if ($device->status === BiometricDeviceStatusEnum::Inactive) {
            $device->update([
                'status' => BiometricDeviceStatusEnum::Active,
                'last_heartbeat_at' => now(),
            ]);
            Cache::forget(BiometricDevice::missCounterCacheKey($device->id));
        }

        if (! $device->adms_enabled) {
            return response()->json(['error' => 'ADMS push not enabled for this device'], 403);
        }

        $body = $request->getContent();

        if (blank($body)) {
            return response()->json(['error' => 'Empty request body'], 400);
        }

        if (strlen($body) > 1048576) { // 1MB limit
            Log::warning('Hikvision: Payload exceeds 1MB limit', [
                'device_id' => $device->id,
                'bytes' => strlen($body),
            ]);

            return response()->json(['error' => 'Payload exceeds size limit'], 413);
        }

        try {
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET);
        } catch (Throwable $e) {
            Log::warning('Hikvision: Invalid XML', [
                'device_id' => $device->id,
            ]);

            return response()->json(['error' => 'Invalid XML'], 400);
        }

        if ($xml === false) {
            return response()->json(['error' => 'Failed to parse XML'], 400);
        }

        // Extract event data from Hikvision XML structure
        $eventType = (string) ($xml->eventType ?? '');

        if ($eventType !== 'AccessControllerEvent') {
            // Acknowledge non-access events silently
            return response()->json(['status' => 'ignored']);
        }

        $accessEvent = $xml->AccessControllerEvent ?? null;

        if (! $accessEvent) {
            return response()->json(['error' => 'Missing AccessControllerEvent'], 400);
        }

        $employeeNo = (string) ($accessEvent->employeeNoString ?? '');
        $dateTimeStr = (string) ($xml->dateTime ?? '');
        $currentVerifyMode = (string) ($accessEvent->currentVerifyMode ?? '');

        if (blank($employeeNo)) {
            Log::warning('Hikvision: Missing employeeNoString', [
                'device_id' => $device->id,
            ]);

            return response()->json(['error' => 'Missing employeeNoString'], 400);
        }

        try {
            $timestamp = BiometricTimestampParser::parse($dateTimeStr, $device);
        } catch (Throwable) {
            Log::warning('Hikvision: Invalid timestamp', [
                'device_id' => $device->id,
            ]);

            return response()->json(['error' => 'Invalid timestamp format'], 400);
        }

        $verifyMode = $this->mapVerifyMode($currentVerifyMode);
        $direction = strtolower((string) ($accessEvent->entryOrExit ?? $accessEvent->direction ?? $accessEvent->attendanceStatus ?? ''));
        $status = in_array($direction, ['checkout', 'exit', 'out', 'leave'], true) ? 1 : 0;

        // Update heartbeat and reset miss counter
        $device->update(['last_heartbeat_at' => now()]);
        Cache::forget(BiometricDevice::missCounterCacheKey($device->id));

        // Durable receipt fingerprint
        $eventSerial = (string) ($xml->eventSerialNo ?? $accessEvent->serialNo ?? '');
        $fingerprint = $this->receiptService->generateFingerprint($device->id, $employeeNo, $timestamp, $eventSerial);
        $summary = [
            'employee_hash' => hash('sha256', $employeeNo),
            'timestamp' => $dateTimeStr,
            'event_serial' => $eventSerial,
        ];

        try {
            $execution = $this->receiptService->executeIdempotent(
                device: $device,
                fingerprint: $fingerprint,
                summary: $summary,
                sideEffect: fn () => $this->admsEventService->processEvent(
                    device: $device,
                    userId: $employeeNo,
                    timestamp: $timestamp,
                    status: $status,
                    verifyMode: $verifyMode
                )
            );
        } catch (Throwable $e) {
            Log::error('Hikvision: Event processing failed due to system exception', [
                'device_id' => $device->id,
                'employee_hash' => hash('sha256', $employeeNo),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Internal processing error'], 500);
        }

        if ($execution->isDuplicate) {
            $isProcessed = $execution->receipt->status === BiometricReceiptStatusEnum::PROCESSED;
            $isIgnored = $execution->receipt->status === BiometricReceiptStatusEnum::IGNORED;
            /** @var array<string, mixed> $payloadSummary */
            $payloadSummary = is_array($execution->receipt->payload_summary) ? $execution->receipt->payload_summary : [];
            $action = $payloadSummary['action'] ?? ($isProcessed ? 'check_in' : ($isIgnored ? 'ignored' : 'denied'));
            $denialReason = $payloadSummary['denial_reason'] ?? null;

            return response()->json([
                'status' => $isProcessed ? 'granted' : ($isIgnored ? 'ignored' : 'denied'),
                'action' => 'idempotent_duplicate',
                'original_action' => $action,
                'denial_reason' => $denialReason,
            ]);
        }

        $result = $execution->eventResult;
        if (! $result) {
            return response()->json([
                'status' => 'denied',
                'action' => 'denied',
                'denial_reason' => null,
            ]);
        }

        return response()->json([
            'status' => $result->granted ? 'granted' : ($result->action === 'ignored' ? 'ignored' : 'denied'),
            'action' => $result->action,
            'denial_reason' => $result->denialReason,
        ]);
    }

    /**
     * Map Hikvision verifyMode string to numeric ATTLOG code.
     */
    private function mapVerifyMode(string $mode): int
    {
        return match (strtolower($mode)) {
            'fingerprint', 'finger' => 1,
            'card' => 2,
            'palm' => 9,
            'face' => 15,
            default => 0,
        };
    }
}
