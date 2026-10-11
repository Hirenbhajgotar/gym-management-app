<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\Services\AdmsEventService;
use App\Biometrics\Services\BiometricReceiptService;
use App\Biometrics\Services\BiometricTimestampParser;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeviceStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ADMS (Automatic Device Management System) protocol handler.
 *
 * Speaks the native iClock protocol used by eSSL, ZKTeco, and compatible devices.
 * Three endpoints:
 *   POST /iclock/cdata?SN={serial}&table=ATTLOG  → receive attendance events
 *   GET  /iclock/cdata?SN={serial}                → initial handshake
 *   GET  /iclock/getrequest?SN={serial}           → device polls for pending commands
 *   POST /iclock/devicecmd?SN={serial}            → device reports command results
 */
class AdmsController extends Controller
{
    public function __construct(
        protected AdmsEventService $admsEventService,
        protected BiometricReceiptService $receiptService
    ) {}

    /**
     * Handle cdata endpoint — both GET (handshake) and POST (attendance data).
     */
    public function cdata(Request $request): Response
    {
        if (! config()->boolean('biometrics.adms.enabled', true)) {
            return $this->plainResponse('ERR: ADMS disabled', 403);
        }

        $serial = $request->query('SN');

        if (! is_string($serial) || blank($serial)) {
            return $this->plainResponse('ERR: Missing SN parameter', 400);
        }

        $device = $this->resolveDevice($serial, $request);

        if (! $device) {
            return $this->plainResponse('ERR: Unknown or inactive device', 401);
        }

        // GET = initial handshake from device
        if ($request->isMethod('GET')) {
            return $this->handleHandshake($device);
        }

        // POST = attendance data push
        $table = $request->query('table', '');

        if (strtoupper((string) $table) !== 'ATTLOG') {
            Log::warning('ADMS: Unsupported table requested', [
                'device_id' => $device->id,
                'table' => $table,
            ]);

            return $this->plainResponse('ERR: Unsupported table', 400);
        }

        return $this->handleAttlog($device, $request);
    }

    /**
     * Device polls for pending commands.
     */
    public function getRequest(Request $request): Response
    {
        if (! config()->boolean('biometrics.adms.enabled', true)) {
            return $this->plainResponse('ERR: ADMS disabled', 403);
        }

        $serial = $request->query('SN');

        if (! is_string($serial) || blank($serial)) {
            return $this->plainResponse('ERR: Missing SN parameter', 400);
        }

        $device = $this->resolveDevice($serial, $request);

        if (! $device) {
            return $this->plainResponse('ERR: Unknown or inactive device', 401);
        }

        // Keep heartbeat fresh on polling
        $device->update(['last_heartbeat_at' => now()]);
        Cache::forget(BiometricDevice::missCounterCacheKey($device->id));

        // Dequeue the oldest pending command for this device atomically
        $leaseDuration = config()->integer('biometrics.adms.command_lease_seconds', 600);
        /** @var AdmsDeviceCommand|null $command */
        $command = AdmsDeviceCommand::leaseNext($device->id, $leaseDuration);

        if (! $command) {
            return $this->plainResponse('OK');
        }

        $commandString = $command->toAdmsCommandString();

        Log::info('ADMS: Command sent to device', [
            'device_id' => $device->id,
            'command_id' => $command->id,
            'command_type' => $command->command_type,
        ]);

        return $this->plainResponse($commandString);
    }

    /**
     * Device reports command execution result.
     */
    public function deviceCmd(Request $request): Response
    {
        if (! config('biometrics.adms.enabled', true)) {
            return $this->plainResponse('ERR: ADMS disabled', 403);
        }

        $serial = $request->query('SN');

        if (! is_string($serial) || blank($serial)) {
            return $this->plainResponse('ERR: Missing SN parameter', 400);
        }

        $device = $this->resolveDevice($serial, $request);

        if (! $device) {
            return $this->plainResponse('ERR: Unknown or inactive device', 401);
        }

        // Keep heartbeat fresh on command ACK
        $device->update(['last_heartbeat_at' => now()]);
        Cache::forget(BiometricDevice::missCounterCacheKey($device->id));

        // Parse response body: ID={cmdId}&Return={code}
        $body = $request->getContent();
        $params = [];
        parse_str($body, $params);

        $rawCommandId = $params['ID'] ?? null;
        $commandId = is_string($rawCommandId) ? $rawCommandId : null;
        $rawReturn = $params['Return'] ?? null;
        $returnCode = is_string($rawReturn) ? $rawReturn : null;

        if ($commandId === null) {
            return $this->plainResponse('OK');
        }

        /** @var AdmsDeviceCommand|null $command */
        $command = AdmsDeviceCommand::query()
            ->where('id', (int) $commandId)
            ->where('device_id', $device->id)
            ->first();

        if ($command && $command->status === AdmsCommandStatusEnum::SENT) {
            $acknowledged = $command->markAcknowledged($returnCode);

            if ($acknowledged) {
                Log::info('ADMS: Command acknowledged', [
                    'device_id' => $device->id,
                    'command_id' => $command->id,
                    'return_code' => $returnCode,
                    'status' => $command->fresh()?->status,
                ]);
            }
        }

        return $this->plainResponse('OK');
    }

    /**
     * Resolve device and verify it is active and ADMS-enabled.
     */
    private function resolveDevice(string $serial, ?Request $request = null): ?BiometricDevice
    {
        if ($request && $request->attributes->has('device')) {
            /** @var BiometricDevice $device */
            $device = $request->attributes->get('device');

            return $device;
        }

        $device = BiometricDevice::findBySerial($serial);

        if (! $device || ! $device->adms_enabled) {
            return null;
        }

        if ($device->status === BiometricDeviceStatusEnum::Maintenance) {
            return null;
        }

        $requireKey = config()->boolean('biometrics.adms.require_comm_key', true);
        $hasKeyConfigured = $device->hasCommKeyConfigured();

        if (($requireKey || $hasKeyConfigured) && $request) {
            $providedKey = $request->query('pushcommkey')
                ?? $request->input('pushcommkey')
                ?? $request->header('pushcommkey')
                ?? $request->header('X-PushCommKey');

            if (! is_string($providedKey) || blank($providedKey) || ! $device->verifyCommKey($providedKey)) {
                Log::warning('ADMS: Invalid pushcommkey', [
                    'device_id' => $device->id,
                    'ip' => $request->ip(),
                ]);

                return null;
            }
        }

        // Auto-recover Inactive device if authenticated communication resumes
        if ($device->status === BiometricDeviceStatusEnum::Inactive) {
            $device->update([
                'status' => BiometricDeviceStatusEnum::Active,
                'last_heartbeat_at' => now(),
            ]);
            Cache::forget(BiometricDevice::missCounterCacheKey($device->id));
            Log::info('ADMS: Device auto-recovered to Active status upon verified communication', [
                'device_id' => $device->id,
                'serial_hash' => hash('sha256', (string) $device->device_serial),
            ]);
        }

        return $device;
    }

    /**
     * Handle initial GET handshake from device.
     */
    private function handleHandshake(BiometricDevice $device): Response
    {
        // Update last heartbeat and clear missed heartbeats counter
        $device->update(['last_heartbeat_at' => now()]);
        Cache::forget(BiometricDevice::missCounterCacheKey($device->id));

        Log::info('ADMS: Device handshake', [
            'device_id' => $device->id,
        ]);

        return $this->plainResponse('OK');
    }

    /**
     * Parse and process ATTLOG attendance records.
     *
     * Body format (tab-separated, one record per line):
     *   UserID\tTimestamp\tStatus\tVerifyMode\tWorkCode
     *   Example: 42\t2026-09-22 10:30:00\t0\t15\t0
     */
    private function handleAttlog(BiometricDevice $device, Request $request): Response
    {
        // Update heartbeat
        $device->update(['last_heartbeat_at' => now()]);
        Cache::forget(BiometricDevice::missCounterCacheKey($device->id));

        $body = $request->getContent();

        if (strlen($body) > 2097152) { // 2MB limit
            Log::warning('ADMS: Payload exceeds 2MB limit', [
                'device_id' => $device->id,
                'bytes' => strlen($body),
            ]);

            return $this->plainResponse('ERR: Payload exceeds size limit', 413);
        }

        $lines = array_filter(explode("\n", trim($body)), fn (string $line): bool => trim($line) !== '');

        if (count($lines) > 500) {
            Log::warning('ADMS: Batch exceeds line limit', [
                'device_id' => $device->id,
                'count' => count($lines),
            ]);

            return $this->plainResponse('ERR: Batch exceeds line limit', 413);
        }

        $processed = 0;
        $systemErrors = 0;
        $malformedErrors = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (blank($line)) {
                continue;
            }

            $fields = explode("\t", $line);
            if (count($fields) < 4) {
                Log::warning('ADMS: Malformed ATTLOG line', [
                    'device_id' => $device->id,
                    'field_count' => count($fields),
                ]);
                $malformedErrors++;

                continue;
            }

            $userId = trim($fields[0]);
            $timestampStr = trim($fields[1]);
            $status = (int) trim($fields[2]);
            $verifyMode = (int) trim($fields[3]);

            try {
                $timestamp = BiometricTimestampParser::parse($timestampStr, $device);
            } catch (Throwable) {
                Log::warning('ADMS: Invalid timestamp', [
                    'device_id' => $device->id,
                ]);
                $malformedErrors++;

                continue;
            }

            $fingerprint = $this->receiptService->generateFingerprint($device->id, $userId, $timestamp, $status);
            $summary = [
                'user_id_hash' => hash('sha256', $userId),
                'timestamp' => $timestamp->toISOString(),
                'status' => $status,
            ];

            try {
                $this->receiptService->executeIdempotent(
                    device: $device,
                    fingerprint: $fingerprint,
                    summary: $summary,
                    sideEffect: fn () => $this->admsEventService->processEvent(
                        device: $device,
                        userId: $userId,
                        timestamp: $timestamp,
                        status: $status,
                        verifyMode: $verifyMode
                    )
                );

                $processed++;
            } catch (Throwable $e) {
                Log::error('ADMS: Event processing failed due to system exception', [
                    'device_id' => $device->id,
                    'user_id_hash' => hash('sha256', $userId),
                    'error' => $e->getMessage(),
                ]);
                $systemErrors++;
            }
        }

        Log::info('ADMS: ATTLOG batch processed', [
            'device_id' => $device->id,
            'processed' => $processed,
            'malformed_errors' => $malformedErrors,
            'system_errors' => $systemErrors,
        ]);

        if ($systemErrors > 0) {
            Log::warning('ADMS: ATTLOG batch encountered system errors, requesting terminal retry', [
                'device_id' => $device->id,
                'system_errors' => $systemErrors,
                'processed' => $processed,
            ]);

            return $this->plainResponse('ERR: RETRY', 500);
        }

        return $this->plainResponse('OK');
    }

    /**
     * ADMS protocol requires plain-text responses (no JSON).
     */
    private function plainResponse(string $content, int $status = 200): Response
    {
        return response($content, $status)
            ->header('Content-Type', 'text/plain');
    }
}
