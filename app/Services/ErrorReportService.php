<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCategory;
use App\Enums\ErrorPriority;
use App\Models\ErrorReport;
use App\Services\ErrorReporting\ExceptionMarkdownFormatter;
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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            errorMessage: $exception->getMessage(),
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
        return [
            'requested_url' => $origin['requested_url'],
            'origin_url' => $origin['origin_url'],
            'livewire_component' => $origin['livewire_component'],
            'method' => request()->method(),
            'user_agent' => request()->header('User-Agent'),
            'ip' => request()->ip(),
            'referer' => request()->header('Referer'),
            'route' => request()->route()?->getName(),
            'request_data' => $this->sanitizeRequestData(request()->all()),
        ];
    }

    private function extractExceptionData(?Throwable $exception = null, ?string $exceptionId = null): array
    {
        $stackTrace = null;
        $errorHash = null;
        $file = null;
        $line = null;
        $exceptionClass = null;
        $errorMessage = '';

        if ($exceptionId) {
            $exceptionData = $this->exceptionStorage->retrieve($exceptionId);

            if ($exceptionData) {
                $stackTrace = $exceptionData['trace'] ?? null;
                $file = $exceptionData['file'] ?? null;
                $line = $exceptionData['line'] ?? null;
                $exceptionClass = $exceptionData['class'] ?? null;
                $errorMessage = $exceptionData['message'] ?? '';
            }
        } elseif ($exception) {
            $stackTrace = $exception->getTraceAsString();
            $file = $exception->getFile();
            $line = $exception->getLine();
            $exceptionClass = get_class($exception);
            $errorHash = $this->generateErrorHash($exception);
            $errorMessage = $exception->getMessage();
        }

        return [
            'errorMessage' => $errorMessage,
            'stackTrace' => $stackTrace,
            'file' => $file,
            'line' => $line,
            'exceptionClass' => $exceptionClass,
            'errorHash' => $errorHash,
        ];
    }

    private function sanitizeRequestData(array $data): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key', 'secret'];

        array_walk_recursive($data, function (&$value, $key) use ($sensitiveFields) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveFields, true)) {
                $value = '***REDACTED***';
            }
        });

        return $data;
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
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'class' => get_class($exception),
        ];

        return hash('sha256', json_encode($data));
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
            $message = 'Validation failed';
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

        if ($exception instanceof HttpException) {
            $status = $exception->getStatusCode();

            if (view()->exists("errors.filament.{$status}")) {
                return response()->view("errors.filament.{$status}", [
                    'exception' => $exception,
                    'reportable' => true,
                ], $status);
            }
        }

        return response()->view('errors.filament.500', [
            'exception' => $exception,
            'reportable' => true,
        ], 500);
    }
}
