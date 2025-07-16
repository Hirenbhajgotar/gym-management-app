<?php

namespace App\Services;

use App\Enums\ErrorPriority;
use App\Models\ErrorReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ErrorReportService
{
    public function reportError(string $errorMessage, ?string $userDescription = null, ?Throwable $exception = null, string $errorType = 'user_reported', string $priority = 'medium', string $error_category = 'other'): ErrorReport
    {
        $context = [
            'url' => Request::url(),
            'method' => Request::method(),
            'user_agent' => Request::header('User-Agent'),
            'ip' => Request::ip(),
            'referer' => Request::header('Referer'),
            'route' => Request::route()?->getName(),
            'request_data' => $this->sanitizeRequestData(Request::all()),
        ];

        $stackTrace = null;
        $errorHash = null;

        if ($exception) {
            $stackTrace = $exception->getTraceAsString();
            $context['file'] = $exception->getFile();
            $context['line'] = $exception->getLine();
            $context['exception_class'] = get_class($exception);
            $errorHash = $this->generateErrorHash($exception);
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
            'category' => $error_category,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);
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
        // Critical errors
        if (
            $exception instanceof \Error ||
            $exception instanceof \ParseError ||
            $exception instanceof \TypeError
        ) {
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
        if (
            $exception instanceof \Error ||
            $exception instanceof \ParseError ||
            $exception instanceof \TypeError
        ) {
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
