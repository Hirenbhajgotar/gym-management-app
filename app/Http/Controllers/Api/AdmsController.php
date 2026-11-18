<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\Services\AdmsEventService;
use App\Biometrics\Services\BiometricLogRedactor;
use App\Biometrics\Services\BiometricReceiptService;
use App\Biometrics\Services\BiometricTimestampParser;
use App\Enums\AdmsCommandStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\User;
use App\Services\ErrorReporting\ExceptionTraceFormatter;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ADMS (Automatic Device Management System) protocol handler.
 *
 * Speaks the native iClock protocol used by eSSL, ZKTeco, and compatible devices.
 * Four operations across three endpoints. Each endpoint is registered on both the
 * `/api/iclock/*` and unprefixed `/iclock/*` surfaces, with an `.aspx`-suffixed
 * alias on each:
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

        $device = $this->resolveDevice($request);

        if (! $device) {
            return $this->plainResponse('ERR: Unauthorized', 401);
        }

        // GET = initial handshake from device
        if ($request->isMethod('GET')) {
            return $this->handleHandshake($device, $request);
        }

        // POST = attendance or device operational data push
        $table = strtoupper((string) $request->query('table', ''));
        $content = $request->getContent();
        $byteCount = is_string($content) ? strlen($content) : 0;

        Log::info('ADMS: cdata payload received', [
            'device_id' => $device->id,
            'serial' => $device->device_serial,
            'table' => $table,
            'query' => $request->query(),
            'bytes' => $byteCount,
            'ip' => $request->ip(),
        ]);

        if ($table === 'OPERLOG') {
            return $this->handleOperlog($device, $request);
        }

        if (in_array($table, ['USERINFO', 'USER', 'BIODATA', 'FINGERTMP', 'OPTIONS'], true)) {
            return $this->handleDeviceDataPush($device, $request, $table);
        }

        if ($table !== 'ATTLOG') {
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

        $device = $this->resolveDevice($request);

        if (! $device) {
            return $this->plainResponse('ERR: Unauthorized', 401);
        }

        // Keep heartbeat fresh on polling
        $device->update(['last_heartbeat_at' => now()]);
        BiometricDevice::clearMissCounter($device->id);

        // Dequeue the oldest pending command for this device atomically
        $leaseDuration = config()->integer('biometrics.adms.command_lease_seconds', 600);
        /** @var AdmsDeviceCommand|null $command */
        $command = AdmsDeviceCommand::leaseNext($device->id, $leaseDuration);

        if (! $command) {
            return $this->plainResponse('OK');
        }

        $commandString = $command->toAdmsCommandString();

        if ($commandString === '') {
            // The lease and the serializer must agree on what is deliverable. If they
            // ever diverge, returning the row to the queue is the only safe move:
            // sending an empty body would look like "no command" while having already
            // consumed one of the command's few hardware deliveries.
            $command->releaseUnusableLease();

            Log::warning('ADMS: leased a command that cannot be expressed; returned it to the queue', [
                'command_id' => $command->id,
                'device_id' => $device->id,
                'command_type' => $command->command_type,
            ]);

            return $this->plainResponse('OK');
        }

        return $this->plainResponse(rtrim($commandString) . "\r\n");
    }

    /**
     * Device reports command execution result.
     */
    public function deviceCmd(Request $request): Response
    {
        if (! config()->boolean('biometrics.adms.enabled', true)) {
            return $this->plainResponse('ERR: ADMS disabled', 403);
        }

        $device = $this->resolveDevice($request);

        if (! $device) {
            return $this->plainResponse('ERR: Unauthorized', 401);
        }

        // Keep heartbeat fresh on command ACK
        $device->update(['last_heartbeat_at' => now()]);
        BiometricDevice::clearMissCounter($device->id);

        // Parse response body: ID={cmdId}&Return={code}
        $body = $request->getContent();
        $params = [];
        parse_str($body, $params);

        Log::info('ADMS: devicecmd received from device', [
            'device_id' => $device->id,
            'serial' => $device->device_serial,
            'raw_body' => $body,
            'parsed_params' => $params,
            'ip' => $request->ip(),
        ]);

        $rawCommandId = $params['ID'] ?? null;
        $commandId = is_string($rawCommandId) ? $rawCommandId : null;
        $rawReturn = $params['Return'] ?? null;
        $returnCode = is_string($rawReturn) ? $rawReturn : null;

        if ($commandId === null) {
            Log::warning('ADMS: devicecmd missing command ID in body', [
                'device_id' => $device->id,
                'raw_body' => $body,
            ]);

            return $this->plainResponse('OK');
        }

        /** @var AdmsDeviceCommand|null $command */
        $command = AdmsDeviceCommand::query()
            ->where('id', (int) $commandId)
            ->where('device_id', $device->id)
            ->first();

        if (! $command) {
            Log::warning('ADMS: devicecmd command not found', [
                'device_id' => $device->id,
                'command_id' => $commandId,
                'return_code' => $returnCode,
                'raw_body' => $body,
            ]);

            return $this->plainResponse('OK');
        }

        if ($command->status === AdmsCommandStatusEnum::SENT) {
            $acknowledged = $command->markAcknowledged($returnCode);

            if ($acknowledged) {
                Log::info('ADMS: Command acknowledged', [
                    'device_id' => $device->id,
                    'serial' => $device->device_serial,
                    'command_id' => $command->id,
                    'command_type' => $command->command_type,
                    'return_code' => $returnCode,
                    'status' => $command->fresh()?->status?->value,
                ]);

                if ($command->command_type === 'reboot' && $command->fresh()?->status === AdmsCommandStatusEnum::ACKNOWLEDGED) {
                    $this->notifyRebootInitiator($command, $device);
                }
            } else {
                Log::warning('ADMS: Command acknowledgement failed', [
                    'device_id' => $device->id,
                    'serial' => $device->device_serial,
                    'command_id' => $command->id,
                    'command_type' => $command->command_type,
                    'return_code' => $returnCode,
                    'status' => $command->fresh()?->status?->value,
                ]);
            }
        } else {
            Log::warning('ADMS: devicecmd received for non-SENT command', [
                'device_id' => $device->id,
                'command_id' => $command->id,
                'current_status' => $command->status->value,
                'return_code' => $returnCode,
                'raw_body' => $body,
            ]);
        }

        return $this->plainResponse('OK');
    }

    /**
     * Resolve the device authenticated and bound by AuthenticateAdmsDevice.
     *
     * The controller deliberately reads only the ADMS-specific attribute and
     * performs no second key check: a route protected by a different device
     * middleware must fail closed rather than skip comm-key verification.
     */
    private function resolveDevice(Request $request): ?BiometricDevice
    {
        $device = $request->attributes->get('adms_device');

        if ($device instanceof BiometricDevice) {
            return $device;
        }

        Log::warning('ADMS: controller reached without an authenticated ADMS device attribute', [
            'ip' => $request->ip(),
        ]);

        return null;
    }

    /**
     * Handle initial GET handshake from device.
     *
     * When the terminal queries options (options=all or pushver present), the server responds
     * with the official ZKTeco Push SDK configuration payload containing TransFlag directives
     * (including EnrollUser and ChgUser) to enable remote user profile and biometric synchronization.
     * When requested without options parameters (e.g. legacy heartbeat check), returns 'OK'.
     */
    private function handleHandshake(BiometricDevice $device, Request $request): Response
    {
        // Update last heartbeat and clear missed heartbeats counter
        $device->update(['last_heartbeat_at' => now()]);
        BiometricDevice::clearMissCounter($device->id);

        $sentReboot = AdmsDeviceCommand::query()
            ->where('device_id', $device->id)
            ->where('command_type', 'reboot')
            ->where('status', AdmsCommandStatusEnum::SENT)
            ->first();

        if ($sentReboot && $sentReboot->markAcknowledged('0')) {
            $this->notifyRebootInitiator($sentReboot, $device);
        }

        Log::info('ADMS: Device handshake', [
            'device_id' => $device->id,
            'serial' => $device->device_serial,
            'query' => $request->query(),
            'ip' => $request->ip(),
        ]);

        if ($request->query('options') !== null || $request->has('pushver')) {
            $serial = $device->device_serial ?? (string) $request->query('SN', '');
            $options = implode("\n", [
                "GET OPTION FROM: {$serial}",
                'Stamp=9999',
                'OpStamp=9999',
                'ErrorDelay=60',
                'Delay=30',
                'TransTimes=00:00;14:05',
                'TransInterval=1',
                "TransFlag=TransData AttLog\tOpLog\tAttPhoto\tEnrollUser\tChgUser\tEnrollFP\tChgFP\tFPImag\tFACE\tUserPic",
                'Realtime=1',
                'Encrypt=None',
                'ServerVer=2.4.1',
                'PushProtVer=2.4.1',
                'PushOptionsFlag=1',
                'PushOptions=UserExtFmt,SupportUserPic',
            ]);

            Log::info('ADMS: Device handshake options sent', [
                'device_id' => $device->id,
                'serial' => $serial,
                'options_length' => strlen($options),
            ]);

            return $this->plainResponse($options . "\n");
        }

        return $this->plainResponse('OK');
    }

    /**
     * Handle auxiliary biometric and roster data pushed by devices (USERINFO, BIODATA, FINGERTMP, OPTIONS).
     *
     * Acknowledges the payload with 'OK' and logs content to prevent infinite hardware retry loops
     * and capture device user roster dumps triggered by DATA QUERY commands.
     */
    private function handleDeviceDataPush(BiometricDevice $device, Request $request, string $table): Response
    {
        $device->update(['last_heartbeat_at' => now()]);
        BiometricDevice::clearMissCounter($device->id);

        $body = $request->getContent();

        Log::info("ADMS: {$table} received from device", [
            'device_id' => $device->id,
            'serial' => $device->device_serial,
            'table' => $table,
            'query' => $request->query(),
            'body' => $body,
            'ip' => $request->ip(),
        ]);

        return $this->plainResponse('OK');
    }

    /**
     * Parse and process OPERLOG device operational/audit records.
     *
     * ZKTeco / eSSL devices push OPERLOG whenever user records or device
     * configurations are modified locally on the terminal. Returning 'OK'
     * acknowledges the log batch and prevents infinite retry loops on hardware.
     */
    private function handleOperlog(BiometricDevice $device, Request $request): Response
    {
        $device->update(['last_heartbeat_at' => now()]);
        BiometricDevice::clearMissCounter($device->id);

        $body = $request->getContent();

        Log::info('ADMS: OPERLOG received from device', [
            'device_id' => $device->id,
            'serial' => $device->device_serial,
            'query' => $request->query(),
            'body' => $body,
            'ip' => $request->ip(),
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
        BiometricDevice::clearMissCounter($device->id);

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
                    'error' => BiometricLogRedactor::redactText($e->getMessage()),
                    'trace' => resolve(ExceptionTraceFormatter::class)->format($e),
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
     * Notify the staff member who initiated the device reboot once the terminal acknowledges or rejoins.
     */
    protected function notifyRebootInitiator(AdmsDeviceCommand $command, BiometricDevice $device): void
    {
        $payload = is_array($command->command_payload) ? $command->command_payload : [];

        if (! empty($payload['reboot_notified_at'])) {
            return;
        }

        $userId = $payload['initiated_by_user_id'] ?? null;

        if (! is_int($userId) && ! is_numeric($userId)) {
            return;
        }

        $user = User::query()->find((int) $userId);

        if (! $user) {
            return;
        }

        $payload['reboot_notified_at'] = now()->toIso8601String();
        $command->update(['command_payload' => $payload]);

        $deviceName = filled($device->device_name)
            ? $device->device_name
            : (filled($device->name) ? $device->name : $device->device_serial);

        try {
            Notification::make()
                ->title('Terminal Rebooted Successfully')
                ->body("Biometric terminal {$deviceName} ({$device->device_serial}) has rebooted.")
                ->success()
                ->sendToDatabase($user);
        } catch (Throwable $exception) {
            Log::warning('ADMS: Failed to send reboot database notification', [
                'command_id' => $command->id,
                'device_id' => $device->id,
                'user_id' => $userId,
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * ADMS protocol requires plain-text responses (no JSON).
     */
    private function plainResponse(string $content, int $status = 200): Response
    {
        return response($content, $status)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
