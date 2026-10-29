<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\AdmsEventService;
use App\Biometrics\Services\BiometricLogRedactor;
use App\Biometrics\Services\BiometricReceiptService;
use App\Enums\BiometricDeviceStatusEnum;
use App\Http\Controllers\Api\AdmsController;
use App\Http\Controllers\Api\HikvisionAdmsController;
use App\Models\BiometricDevice;
use App\Models\ErrorReport;
use App\Services\ErrorReportService;
use App\Services\ExceptionStorageService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use PDOException;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class ErrorReportingRound7Test extends TestCase
{
    use RefreshDatabase;

    public function test_global_reporter_handles_a_throwing_route_without_a_class_error(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config(['error-reporting.auto_reporting' => true]);

        $this->app->make('router')->get('/round-seven-throwing-route', static function (): never {
            throw new RuntimeException('original failure pushcommkey=749201');
        });

        $this->get('/round-seven-throwing-route')->assertInternalServerError();

        $report = ErrorReport::query()->latest('id')->first();

        $this->assertInstanceOf(ErrorReport::class, $report);
        $this->assertStringContainsString('original failure', (string) $report->error_message);
        $this->assertStringNotContainsString('749201', (string) $report->error_message);
    }

    public function test_adms_controller_logs_the_original_exception_when_processing_fails(): void
    {
        config(['biometrics.adms.enabled' => true]);
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(static fn (string $message, array $context): bool => $message === 'ADMS: Event processing failed due to system exception'
                && str_contains((string) ($context['error'] ?? ''), '[REDACTED]')
                && ! str_contains((string) ($context['error'] ?? ''), '749201'));

        $device = BiometricDevice::factory()->create();
        $eventService = Mockery::mock(AdmsEventService::class);
        $eventService->shouldReceive('processEvent')
            ->once()
            ->andThrow(new RuntimeException('adms failure pushcommkey=749201'));

        $receiptService = Mockery::mock(BiometricReceiptService::class);
        $receiptService->shouldReceive('generateFingerprint')->once()->andReturn('fingerprint');
        $receiptService->shouldReceive('executeIdempotent')
            ->once()
            ->andReturnUsing(static function (
                BiometricDevice $device,
                string $fingerprint,
                array $summary,
                callable $sideEffect
            ): never {
                $sideEffect();

                throw new RuntimeException('side effect unexpectedly returned');
            });

        $request = Request::create(
            '/api/iclock/cdata?SN=' . urlencode((string) $device->serial_number) . '&table=ATTLOG',
            'POST',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: "1001\t" . now()->format('Y-m-d H:i:s') . "\t0\t1\t0"
        );
        $request->attributes->set('adms_device', $device);

        $response = (new AdmsController($eventService, $receiptService))->cdata($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('ERR: RETRY', $response->getContent());
    }

    public function test_hikvision_controller_logs_the_original_exception_when_processing_fails(): void
    {
        config(['biometrics.adms.enabled' => true]);
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(static fn (string $message, array $context): bool => $message === 'Hikvision: Event processing failed due to system exception'
                && str_contains((string) ($context['error'] ?? ''), '[REDACTED]')
                && ! str_contains((string) ($context['error'] ?? ''), '749201'));

        $device = BiometricDevice::factory()->create([
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
        ]);

        $eventService = Mockery::mock(AdmsEventService::class);
        $eventService->shouldReceive('processEvent')
            ->once()
            ->andThrow(new RuntimeException('hikvision failure pushcommkey=749201'));

        $receiptService = Mockery::mock(BiometricReceiptService::class);
        $receiptService->shouldReceive('generateFingerprint')->once()->andReturn('fingerprint');
        $receiptService->shouldReceive('executeIdempotent')
            ->once()
            ->andReturnUsing(static function (
                BiometricDevice $device,
                string $fingerprint,
                array $summary,
                callable $sideEffect
            ): never {
                $sideEffect();

                throw new RuntimeException('side effect unexpectedly returned');
            });

        $body = str_replace(
            '__NOW__',
            now()->format('Y-m-d\TH:i:sP'),
            <<<'XML'
<EventNotificationAlert>
    <eventType>AccessControllerEvent</eventType>
    <dateTime>__NOW__</dateTime>
    <AccessControllerEvent>
        <employeeNoString>1001</employeeNoString>
        <currentVerifyMode>face</currentVerifyMode>
        <serialNo>1</serialNo>
    </AccessControllerEvent>
</EventNotificationAlert>
XML
        );

        $request = Request::create(
            '/api/v1/biometric/hikvision/event',
            'POST',
            server: ['CONTENT_TYPE' => 'application/xml'],
            content: $body
        );
        $request->attributes->set('device', $device);

        $response = (new HikvisionAdmsController($eventService, $receiptService))->handleEvent($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['error' => 'Internal processing error'], $response->getData(true));
    }

    public function test_error_hash_is_not_derived_from_the_raw_pre_redaction_message(): void
    {
        $exception = new RuntimeException('failed pushcommkey=749201');

        $report = resolve(ErrorReportService::class)->reportException($exception);
        $rawHash = hash('sha256', (string) json_encode([
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'class' => $exception::class,
        ]));

        $this->assertNotSame($rawHash, $report->getRawOriginal('error_hash'));
        $this->assertStringNotContainsString('749201', (string) $report->error_message);
    }

    public function test_query_exception_hash_separates_sqlstate_and_driver_codes(): void
    {
        $service = resolve(ErrorReportService::class);

        $duplicate = $service->reportException($this->queryException('23000', 1062, 'Duplicate entry detected'));
        $deadlock = $service->reportException($this->queryException('40001', 1213, 'Duplicate entry detected'));

        $this->assertSame($duplicate->context['file'], $deadlock->context['file']);
        $this->assertSame($duplicate->context['line'], $deadlock->context['line']);
        $this->assertNotSame(
            $duplicate->getRawOriginal('error_hash'),
            $deadlock->getRawOriginal('error_hash'),
            'A duplicate-key and a deadlock on the same SQL/file/line must not share a hash.'
        );
        $this->assertSame('23000', $duplicate->context['database_error']['sqlstate']);
        $this->assertSame(1062, $duplicate->context['database_error']['driver_code']);
        $this->assertSame('40001', $deadlock->context['database_error']['sqlstate']);
        $this->assertSame(1213, $deadlock->context['database_error']['driver_code']);
    }

    public function test_query_exception_context_redacts_driver_message_and_excludes_bindings(): void
    {
        $service = resolve(ErrorReportService::class);
        $binding = 'customer-749201';

        $report = $service->reportException($this->queryException(
            '23000',
            1062,
            "Duplicate entry '{$binding}' for key 'biometric_logs.payload'",
            [$binding, 'payload'],
        ));

        $encodedContext = (string) json_encode($report->context);

        $this->assertStringNotContainsString($binding, $encodedContext);
        $this->assertStringNotContainsString($binding, (string) $report->getRawOriginal('error_message'));
        $this->assertStringContainsString('[REDACTED]', $report->context['database_error']['driver_message']);

        $firstBinding = $service->reportException($this->queryException(
            '23000',
            1062,
            'Duplicate entry for a unique key at row 4',
            ['customer-749201'],
        ));
        $secondBinding = $service->reportException($this->queryException(
            '23000',
            1062,
            'Duplicate entry for a unique key at row 4',
            ['someone-else-42'],
        ));

        $this->assertSame(
            $firstBinding->getRawOriginal('error_hash'),
            $secondBinding->getRawOriginal('error_hash'),
            'Bindings must not participate in the error hash.'
        );
    }

    public function test_query_exception_hash_is_derived_from_the_redacted_sql(): void
    {
        $sql = "select * from biometric_devices where pushcommkey = 'ABCDEF'";
        $exception = $this->queryException('23000', 1062, 'Duplicate entry detected', [], $sql);
        $redactedSql = BiometricLogRedactor::redactText($sql);

        $hash = $this->generateErrorHash($exception);

        $expectedHash = hash('sha256', (string) json_encode([
            'message' => $redactedSql,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'class' => $exception::class,
            'sqlstate' => '23000',
            'driver_code' => 1062,
            'driver_message' => BiometricLogRedactor::redactText('Duplicate entry detected'),
        ]));

        $rawHash = hash('sha256', (string) json_encode([
            'message' => $sql,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'class' => $exception::class,
            'sqlstate' => '23000',
            'driver_code' => 1062,
            'driver_message' => BiometricLogRedactor::redactText('Duplicate entry detected'),
        ]));

        $this->assertStringNotContainsString('ABCDEF', $redactedSql);
        $this->assertSame(
            $expectedHash,
            $hash,
            'The error hash must be computed from the redacted SQL, not the raw literal.'
        );
        $this->assertNotSame($rawHash, $hash, 'The raw SQL literal must not enter the hash input.');
    }

    public function test_query_exception_sql_literals_are_redacted_in_the_stored_error_message(): void
    {
        $exception = $this->queryException(
            '23000',
            1062,
            'Duplicate entry detected',
            [],
            "select * from biometric_devices where pushcommkey = 'ABCDEF'",
        );

        $report = resolve(ErrorReportService::class)->reportException($exception);
        $storedMessage = (string) $report->getRawOriginal('error_message');

        $this->assertStringNotContainsString('ABCDEF', $storedMessage);
        $this->assertStringContainsString('[REDACTED]', $storedMessage);
    }

    public function test_query_exception_sql_literals_are_redacted_in_the_session_payload(): void
    {
        $exception = $this->queryException(
            '23000',
            1062,
            'Duplicate entry detected',
            [],
            "select * from biometric_devices where pushcommkey = 'ABCDEF'",
        );

        $storage = resolve(ExceptionStorageService::class);
        $id = $storage->store($exception);
        $stored = $storage->retrieve($id);

        $this->assertIsArray($stored);
        $this->assertStringNotContainsString('ABCDEF', (string) ($stored['message'] ?? ''));
        $this->assertStringContainsString('[REDACTED]', (string) ($stored['message'] ?? ''));
    }

    private function generateErrorHash(QueryException $exception): string
    {
        $method = new ReflectionMethod(ErrorReportService::class, 'generateErrorHash');
        $method->setAccessible(true);

        return (string) $method->invoke(resolve(ErrorReportService::class), $exception);
    }

    /**
     * Build a QueryException with controlled SQLSTATE, driver code, and driver
     * message. Both callers get the same file/line because the exception is
     * constructed in this single helper frame.
     *
     * @param  array<int, mixed>  $bindings
     */
    private function queryException(
        string $sqlState,
        int $driverCode,
        string $driverMessage,
        array $bindings = [],
        ?string $sql = null,
    ): QueryException {
        $previous = new class($driverMessage, $sqlState, $driverCode) extends PDOException
        {
            public function __construct(string $message, string $sqlState, int $driverCode)
            {
                parent::__construct($message, 0);

                $this->code = $sqlState;
                $this->errorInfo = [$sqlState, $driverCode, $message];
            }
        };

        return new QueryException(
            'mysql',
            $sql ?? 'insert into `biometric_logs` (`device_id`, `payload`) values (?, ?)',
            $bindings,
            $previous,
        );
    }
}
