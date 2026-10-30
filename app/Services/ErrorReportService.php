<?php

declare(strict_types=1);

namespace App\Services;

use App\Biometrics\Services\BiometricLogRedactor;
use App\Enums\ErrorCategory;
use App\Enums\ErrorPriority;
use App\Models\ErrorReport;
use App\Services\ErrorReporting\ExceptionMarkdownFormatter;
use App\Services\ErrorReporting\ExceptionTraceFormatter;
use App\Services\ErrorReporting\RequestOriginResolver;
use Error;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mailer\Exception\TransportException;
use Throwable;

class ErrorReportService
{
    public function __construct(
        private readonly RequestOriginResolver $originResolver,
        private readonly ExceptionMarkdownFormatter $markdownFormatter,
        private readonly ExceptionTraceFormatter $traceFormatter,
        private readonly ExceptionStorageService $exceptionStorage,
    ) {}

    public function reportError(
        string $errorMessage,
        ?string $userDescription = null,
        ?Throwable $exception = null,
        string $errorType = 'user_reported',
        string $priority = ErrorPriority::MEDIUM->value,
        string $errorCategory = ErrorCategory::OTHER->value,
        ?string $exceptionId = null,
    ): ErrorReport {
        $errorMessage = BiometricLogRedactor::redactText($errorMessage);
        $userDescription = $userDescription !== null
            ? BiometricLogRedactor::redactText($userDescription)
            : null;

        try {
            /** @var array{requested_url: string, origin_url: string, livewire_component: string|null} $origin */
            $origin = $this->originResolver->resolve(request());
            $context = $this->buildContext($origin);

            $exceptionData = $this->extractExceptionData($exception, $exceptionId);

            $context['file'] = $exceptionData['file'];
            $context['line'] = $exceptionData['line'];
            $context['exception_class'] = $exceptionData['exceptionClass'];
            $stackTrace = $exceptionData['stackTrace'];
            $errorHash = $exceptionData['errorHash'];

            if ($exceptionData['databaseError'] !== null) {
                $context['database_error'] = $exceptionData['databaseError'];
            }

            $markdownReport = $exception
                ? $this->markdownFormatter->format($exception, request(), $origin)
                : null;

            if ($exceptionId) {
                $this->exceptionStorage->clearExceptionStorageData($exceptionId);
            }

            return ErrorReport::query()->create([
                'user_id' => Auth::id(),
                'error_type' => $errorType,
                'error_message' => $errorMessage,
                'error_hash' => $errorHash,
                'stack_trace' => $stackTrace,
                'markdown_report' => $markdownReport,
                'context' => $context,
                'origin_url' => $origin['origin_url'],
                'livewire_component' => $origin['livewire_component'],
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'user_description' => $userDescription,
                'priority' => $priority,
                'category' => $errorCategory,
                'occurred_at' => now(),
                'last_occurrence' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create ErrorReport', [
                'error' => BiometricLogRedactor::redactText($e->getMessage()),
                'trace' => $this->traceFormatter->format($e),
            ]);
            throw $e;
        }
    }

    public function reportException(Throwable $exception, ?string $userDescription = null, string $reportType = 'exception'): ErrorReport
    {
        $priority = $this->determinePriority($exception);

        if ($reportType === 'auto_reported') {
            $duplicate = $this->findAndIncrementDuplicate($exception);

            if ($duplicate) {
                return $duplicate;
            }
        }

        return $this->reportError(
            errorMessage: $exception instanceof QueryException
                ? BiometricLogRedactor::redactText($exception->getSql())
                : $exception->getMessage(),
            userDescription: $userDescription,
            exception: $exception,
            errorType: $reportType,
            priority: $priority,
        );
    }

    public function manuallyReportException(
        ?string $userDescription = null,
        string $priority = 'medium',
        string $errorCategory = 'other',
        string $errorType = 'user_reported',
        ?string $exceptionId = null,
    ): ErrorReport {
        $exceptionData = $this->extractExceptionData(exceptionId: $exceptionId);

        $errorMessage = $exceptionData['errorMessage'] ?: 'Error message not available or stored. Please check storage.';

        return $this->reportError(
            errorMessage: $errorMessage,
            userDescription: $userDescription,
            priority: $priority,
            errorType: $errorType,
            errorCategory: $errorCategory,
            exceptionId: $exceptionId,
        );
    }

    /**
     * @param  array<string, string|null>  $origin
     */
    private function buildContext(array $origin): array
    {
        $headers = request()->headers->all();
        $redactedHeaders = BiometricLogRedactor::redactHeaders($headers);

        return [
            'requested_url' => $origin['requested_url'],
            'origin_url' => $origin['origin_url'],
            'livewire_component' => $origin['livewire_component'],
            'method' => request()->method(),
            'user_agent' => BiometricLogRedactor::redactText(request()->header('User-Agent')),
            'ip' => request()->ip(),
            'referer' => $this->originResolver->redactUrl((string) request()->header('Referer')),
            'route' => request()->route()?->getName(),
            'headers' => $redactedHeaders,
            'request_data' => BiometricLogRedactor::sanitizeRequestData(request()->all()),
        ];
    }

    /**
     * @return array{
     *     errorMessage: string,
     *     stackTrace: string|null,
     *     file: string|null,
     *     line: int|null,
     *     exceptionClass: string|null,
     *     errorHash: string|null,
     *     databaseError: array{sqlstate: string, driver_code: int|string|null, driver_message: string}|null
     * }
     */
    private function extractExceptionData(?Throwable $exception = null, ?string $exceptionId = null): array
    {
        $stackTrace = null;
        $errorHash = null;
        $file = null;
        $line = null;
        $exceptionClass = null;
        $errorMessage = '';
        $databaseError = null;

        if ($exceptionId) {
            $exceptionData = $this->exceptionStorage->retrieve($exceptionId);

            if ($exceptionData) {
                $storedTrace = $exceptionData['trace'] ?? null;
                $stackTrace = is_string($storedTrace)
                    ? BiometricLogRedactor::redactText($storedTrace)
                    : null;
                $storedFile = $exceptionData['file'] ?? null;
                $file = is_string($storedFile) ? $storedFile : null;
                $storedLine = $exceptionData['line'] ?? null;
                $line = is_int($storedLine) ? $storedLine : null;
                $storedClass = $exceptionData['class'] ?? null;
                $exceptionClass = is_string($storedClass) ? $storedClass : null;
                $storedMessage = $exceptionData['message'] ?? '';
                $errorMessage = is_string($storedMessage)
                    ? BiometricLogRedactor::redactText($storedMessage)
                    : '';
            }
        } elseif ($exception) {
            $stackTrace = $this->traceFormatter->format($exception);
            $file = $exception->getFile();
            $line = $exception->getLine();
            $exceptionClass = get_class($exception);
            $errorHash = $this->generateErrorHash($exception);
            // QueryException interpolates bindings into its message. The SQL
            // with placeholders is enough for diagnosis, but a raw literal in
            // the SQL (e.g. a `whereRaw` key) still needs redaction.
            $errorMessage = $exception instanceof QueryException
                ? BiometricLogRedactor::redactText($exception->getSql())
                : BiometricLogRedactor::redactText($exception->getMessage());

            if ($exception instanceof QueryException) {
                $databaseError = $this->queryExceptionDiagnostics($exception);
            }
        }

        return [
            'errorMessage' => $errorMessage,
            'stackTrace' => $stackTrace,
            'file' => $file,
            'line' => $line,
            'exceptionClass' => $exceptionClass,
            'errorHash' => $errorHash,
            'databaseError' => $databaseError,
        ];
    }

    public function determinePriority(Throwable $exception): string
    {
        try {
            if ($exception instanceof Error) {
                return ErrorPriority::CRITICAL->value;
            }

            if ($exception instanceof QueryException) {
                $errorCode = $exception->getCode();

                if (in_array($errorCode, ['HY000', '42000', '42S02'], true)) {
                    return ErrorPriority::CRITICAL->value;
                }

                return ErrorPriority::HIGH->value;
            }

            // High priority errors
            if ($exception instanceof HttpException) {
                return $exception->getStatusCode() >= 500 ? ErrorPriority::HIGH->value : ErrorPriority::MEDIUM->value;
            }

            // File system errors
            if ($exception instanceof FilesystemException) {
                return ErrorPriority::HIGH->value;
            }

            // Mail errors
            if ($exception instanceof TransportException) {
                return ErrorPriority::HIGH->value;
            }

            // Queue failures
            if ($exception instanceof MaxAttemptsExceededException) {
                return ErrorPriority::HIGH->value;
            }

            $message = $exception->getMessage();
            if (
                str_contains($message, 'memory') ||
                str_contains($message, 'timeout') ||
                str_contains($message, 'execution time')
            ) {
                return ErrorPriority::HIGH->value;
            }

            return ErrorPriority::MEDIUM->value;
        } catch (Exception $e) {
            Log::error('Error determining priority, falling back to medium', [
                'error' => $e->getMessage(),
            ]);

            return ErrorPriority::MEDIUM->value;
        }
    }

    /**
     * Ek hi query me duplicate check + increment kar dete hai —
     * pehle do alag queries chal rahi thi (isDuplicateError + incrementDuplicateCount)
     * jisme race condition ka chance bhi tha.
     */
    private function findAndIncrementDuplicate(Throwable $exception): ?ErrorReport
    {
        $errorHash = $this->generateErrorHash($exception);

        $affected = ErrorReport::query()
            ->where('error_hash', $errorHash)
            ->where('created_at', '>', now()->subHour())
            ->increment('duplicate_count', 1, ['last_occurrence' => now()]);

        if ($affected > 0) {
            return ErrorReport::query()
                ->where('error_hash', $errorHash)
                ->where('created_at', '>', now()->subHour())
                ->first();
        }

        return null;
    }

    private function generateErrorHash(Throwable $exception): string
    {
        $data = [
            // Deduplication must not become an offline oracle for the raw
            // 6-digit comm key. Hash the same redacted message that is stored;
            // a raw literal in QueryException SQL must not enter the hash.
            'message' => $exception instanceof QueryException
                ? BiometricLogRedactor::redactText($exception->getSql())
                : BiometricLogRedactor::redactText($exception->getMessage()),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'class' => get_class($exception),
        ];

        if ($exception instanceof QueryException) {
            $diagnostics = $this->queryExceptionDiagnostics($exception);

            // The same SQL at the same location can fail for different
            // reasons (duplicate key vs deadlock vs missing table). Include the
            // SQLSTATE, driver code, and redacted driver message so those do
            // not collapse into one duplicate group. Bindings are never part
            // of the hash input.
            $data['sqlstate'] = $diagnostics['sqlstate'];
            $data['driver_code'] = $diagnostics['driver_code'];
            $data['driver_message'] = $diagnostics['driver_message'];
        }

        $encoded = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);

        return hash('sha256', is_string($encoded) ? $encoded : implode('|', $data));
    }

    /**
     * Structured, binding-free database diagnostics.
     *
     * The SQL kept in the report and hash uses `?` placeholders; the driver
     * message is redacted and scrubbed of any bound value the driver echoed
     * back (MySQL quotes the offending value in a duplicate-key error).
     *
     * @return array{sqlstate: string, driver_code: int|string|null, driver_message: string}
     */
    private function queryExceptionDiagnostics(QueryException $exception): array
    {
        $errorInfo = $exception->errorInfo;
        $errorInfo = is_array($errorInfo) ? $errorInfo : [];

        $sqlState = (string) $exception->getCode();

        if (($sqlState === '' || $sqlState === '0') && isset($errorInfo[0]) && is_scalar($errorInfo[0])) {
            $sqlState = (string) $errorInfo[0];
        }

        $driverCode = $errorInfo[1] ?? null;

        if (! is_int($driverCode) && ! is_string($driverCode)) {
            $driverCode = null;
        }

        $driverMessage = isset($errorInfo[2]) && is_string($errorInfo[2]) ? $errorInfo[2] : '';

        return [
            'sqlstate' => $sqlState,
            'driver_code' => $driverCode,
            'driver_message' => $this->redactDatabaseDriverMessage($driverMessage, $exception->getBindings()),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    private function redactDatabaseDriverMessage(string $message, array $bindings): string
    {
        $message = BiometricLogRedactor::redactText($message);

        foreach ($bindings as $binding) {
            if (! is_string($binding) && ! is_int($binding) && ! is_float($binding)) {
                continue;
            }

            $value = (string) $binding;

            // Very short bindings (row ids, 0/1 flags) would replace arbitrary
            // digits in the diagnostic and are not credentials.
            if (strlen($value) < 4) {
                continue;
            }

            $message = str_replace($value, '[REDACTED]', $message);
        }

        return $message;
    }

    public function shouldAutoReport(Throwable $exception): bool
    {
        if ($exception instanceof Error) {
            return true;
        }

        if ($exception instanceof QueryException || $exception instanceof PDOException) {
            return true;
        }

        if ($exception instanceof HttpException && $exception->getStatusCode() >= 500) {
            return true;
        }

        if ($exception instanceof FilesystemException) {
            return true;
        }

        if ($exception instanceof TransportException) {
            return true;
        }

        if ($exception instanceof MaxAttemptsExceededException) {
            return true;
        }

        if ($exception instanceof HttpException && $exception->getStatusCode() < 500) {
            return false;
        }

        if ($exception instanceof ValidationException) {
            return false;
        }

        return true;
    }

    public function shouldSkipAutoReporting(Throwable $exception): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        // Tampered/corrupted Livewire payloads are attacker probes (or stale
        // client state after a key rotation), not application bugs. Don't
        // create error reports or page on-call for them.
        if ($exception instanceof CorruptComponentPayloadException) {
            return true;
        }

        // Calls to non-existent Livewire component methods (e.g. the legacy
        // Livewire v2 "emit" method) are attacker probes / stale client state,
        // not application bugs. Don't create error reports or page on-call.
        if ($exception instanceof MethodNotFoundException) {
            return true;
        }

        if ($exception instanceof AuthenticationException) {
            return true;
        }

        if ($exception instanceof AuthorizationException) {
            return true;
        }

        if ($exception instanceof ThrottleRequestsException) {
            return true;
        }

        if ($exception instanceof ModelNotFoundException) {
            return true;
        }

        return false;
    }

    public function renderJsonResponse(Throwable $exception)
    {
        $status = 500;
        $message = 'Internal Server Error';

        if ($exception instanceof HttpException) {
            $status = $exception->getStatusCode();
            $message = $exception->getMessage() ?: 'HTTP Error';
        }

        if ($exception instanceof ValidationException) {
            $status = 422;
            $message = $exception->getMessage() ?: 'Validation failed';

            return response()->json([
                'error' => true,
                'message' => $message,
                'errors' => $exception->errors(),
                'status' => $status,
                'timestamp' => now()->toISOString(),
            ], $status);
        }

        return response()->json([
            'error' => true,
            'message' => $message,
            'status' => $status,
            'timestamp' => now()->toISOString(),
        ], $status);
    }

    public function renderFilamentError($request, Throwable $exception)
    {
        if (config('app.debug')) {
            return null;
        }

        $status = $exception instanceof HttpException ? $exception->getStatusCode() : 500;
        $viewName = "errors.filament.{$status}";

        if (view()->exists($viewName)) {
            return response()->view($viewName, [
                'exception' => $exception,
                'reportable' => true,
            ], $status);
        }

        return null;
    }
}
