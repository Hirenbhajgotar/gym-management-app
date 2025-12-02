<?php

namespace App\Services;

use App\Enums\ErrorPriority;
use App\Models\ErrorReport;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ErrorReportService
{
    public function reportError(string $errorMessage, ?string $userDescription = null, ?Throwable $exception = null, string $errorType = 'user_reported', string $priority = 'medium', string $errorCategory = 'other', ?string $exceptionId = null): ErrorReport
    {
        try {
            $context = $this->buildContext(); // Collect context data once

            // Handle exception or exception ID data
            $exceptionData = $this->extractExceptionData($exception, $exceptionId);

            $context['file'] = $exceptionData['file'];
            $context['line'] = $exceptionData['line'];
            $context['exception_class'] = $exceptionData['exceptionClass'];
            $stackTrace = $exceptionData['stackTrace'];
            $errorHash = $exceptionData['errorHash'];

            if ($exceptionId) {
                resolve(ExceptionStorageService::class)->clearExceptionStorageData($exceptionId);
            }

            return ErrorReport::create([
                'user_id' => Auth::id(),
                'error_type' => $errorType,
                'error_message' => $errorMessage,
                'error_hash' => $errorHash,
                'stack_trace' => $stackTrace,
                'context' => $context,
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

        // Check if this error has already been reported recently
        if ($this->isDuplicateError($exception) && $reportType === 'auto_reported') {
            return $this->incrementDuplicateCount($exception);
        }

        return $this->reportError(
            errorMessage: $exception->getMessage(),
            userDescription: $userDescription,
            exception: $exception,
            errorType: $reportType,
            priority: $priority
        );
    }

    public function manuallyReportException(?string $userDescription = null, string $priority = 'medium', string $errorCategory = 'other', string $errorType = 'user_reported', ?string $exceptionId = null): ErrorReport
    {
        $exceptionData = $this->extractExceptionData(exceptionId: $exceptionId);

        $errorMessage = $exceptionData['errorMessage'] ?? 'Error message not available or stored. Please check storage.';

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
     * Builds the context array with request data
     */
    private function buildContext(): array
    {
        return [
            'url' => request()->url(),
            'method' => request()->method(),
            'user_agent' => request()->header('User-Agent'),
            'ip' => request()->ip(),
            'referer' => request()->header('Referer'),
            'route' => request()->route()?->getName(),
            'request_data' => $this->sanitizeRequestData(request()->all()),
        ];
    }

    /**
     * Extracts stack trace and error hash from exception or exception ID
     */
    private function extractExceptionData(?Throwable $exception = null, ?string $exceptionId = null): array
    {
        $stackTrace = null;
        $errorHash = null;
        $file = null;
        $line = null;
        $exception_class = null;
        $errorMessage = '';

        if ($exceptionId) {
            $exceptionData = resolve(ExceptionStorageService::class)->retrieve($exceptionId);
            if ($exceptionData) {
                $stackTrace = $exceptionData['trace'] ?? null;
                $file = $exceptionData['file'];
                $line = $exceptionData['line'];
                $exception_class = $exceptionData['class'];
                $errorMessage = $exceptionData['message'];
            }
        } elseif ($exception) {
            $stackTrace = $exception->getTraceAsString();
            $file = $exception->getFile();
            $line = $exception->getLine();
            $exception_class = get_class($exception);
            $errorHash = $this->generateErrorHash($exception);
            $errorMessage = $exception->getMessage();
        }

        return [
            'errorMessage' => $errorMessage,
            'stackTrace' => $stackTrace,
            'file' => $file,
            'line' => $line,
            'exceptionClass' => $exception_class,
            'errorHash' => $errorHash,
        ];
    }

    private function sanitizeRequestData(array $data): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key', 'secret'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }

        return $data;
    }

    public function determinePriority(Throwable $exception): string
    {
        try {
            // Critical errors
            if ($exception instanceof \Error) {
                return ErrorPriority::CRITICAL->value;
            }

            // Critical database errors
            if ($exception instanceof \Illuminate\Database\QueryException) {
                // Check for specific critical database errors
                $errorCode = $exception->getCode();
                if (in_array($errorCode, ['HY000', '42000', '42S02'])) {
                    return ErrorPriority::CRITICAL->value;
                }

                return ErrorPriority::HIGH->value;
            }

            // High priority errors
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                return $exception->getStatusCode() >= 500 ? ErrorPriority::HIGH->value : ErrorPriority::MEDIUM->value;
            }

            // File system errors
            if ($exception instanceof FilesystemException) {
                return ErrorPriority::HIGH->value;
            }

            // Mail errors
            if ($exception instanceof \Symfony\Component\Mailer\Exception\TransportException) {
                return ErrorPriority::HIGH->value;
            }

            // Queue failures
            if ($exception instanceof \Illuminate\Queue\MaxAttemptsExceededException) {
                return ErrorPriority::HIGH->value;
            }

            // Memory/timeout errors
            if (
                strpos($exception->getMessage(), 'memory') !== false ||
                strpos($exception->getMessage(), 'timeout') !== false ||
                strpos($exception->getMessage(), 'execution time') !== false
            ) {
                return ErrorPriority::HIGH->value;
            }

            return ErrorPriority::MEDIUM->value;
        } catch (Exception $e) {
            Log::error('Error determining priority, falling back to medium', [
                'error' => $e->getMessage(),
            ]);

            return 'medium'; // Fallback to string value
        }
    }

    /**
     * Check if this error has been reported recently (within last hour)
     */
    private function isDuplicateError(Throwable $exception): bool
    {
        $errorHash = $this->generateErrorHash($exception);

        return ErrorReport::where('error_hash', $errorHash)
            ->where('created_at', '>', now()->subHour())
            ->exists();
    }

    /**
     * Generate a unique hash for the error to detect duplicates
     */
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

    /**
     * Increment the duplicate count for an existing error
     */
    private function incrementDuplicateCount(Throwable $exception): ErrorReport
    {
        $errorHash = $this->generateErrorHash($exception);

        $errorReport = ErrorReport::where('error_hash', $errorHash)
            ->where('created_at', '>', now()->subHour())
            ->first();

        if ($errorReport) {
            $errorReport->increment('duplicate_count');
            $errorReport->update(['last_occurrence' => now()]);
        }

        return $errorReport;
    }

    /**
     * Determine if the exception should be auto-reported
     */
    public function shouldAutoReport(Throwable $exception): bool
    {
        // Always report critical errors
        if ($exception instanceof \Error) {
            return true;
        }

        // Report database errors
        if (
            $exception instanceof \Illuminate\Database\QueryException ||
            $exception instanceof \PDOException
        ) {
            return true;
        }

        // Report 5xx HTTP errors
        if ($exception instanceof HttpException && $exception->getStatusCode() >= 500) {
            return true;
        }

        // Report custom application exceptions
        // if ($exception instanceof \App\Exceptions\CustomException) {
        //     return true;
        // }

        // Report file system errors
        if ($exception instanceof FilesystemException) {
            return true;
        }

        // Report mail errors
        if ($exception instanceof \Symfony\Component\Mailer\Exception\TransportException) {
            return true;
        }

        // Report queue job failures
        if ($exception instanceof \Illuminate\Queue\MaxAttemptsExceededException) {
            return true;
        }

        // Skip 4xx errors and validation errors
        if ($exception instanceof HttpException && $exception->getStatusCode() < 500) {
            return false;
        }

        if ($exception instanceof ValidationException) {
            return false;
        }

        // Report other exceptions by default
        return true;
    }

    /**
     * Determine if we should skip auto-reporting for this exception
     */
    public function shouldSkipAutoReporting(Throwable $exception): bool
    {
        // Skip if in testing environment
        if (app()->environment('testing')) {
            return true;
        }

        // Skip authentication errors
        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            return true;
        }

        // Skip authorization errors
        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return true;
        }

        // Skip maintenance mode
        // if ($exception instanceof \Illuminate\Foundation\Http\Exceptions\MaintenanceModeException) {
        //     return true;
        // }

        // Skip throttling errors
        if ($exception instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
            return true;
        }

        // Skip model not found (404 errors)
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return true;
        }

        return false;
    }

    // Helper functions (you can move these to a separate class if needed)
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
        // For development, show the detailed error
        if (config('app.debug')) {
            return null; // Let Laravel handle it
        }

        // For production, show a user-friendly error page
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
