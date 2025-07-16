<?php

namespace App\Exceptions;

use App\Services\ErrorReportService;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     */
    protected $dontReport = [
        ValidationException::class,
        // Add other exceptions you don't want to auto-report
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function report(Throwable $e)
    {
        // Add debugging log
        Log::info('Handler::report called', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        parent::report($e); // Call the parent report method first
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            Log::info('Reportable callback triggered', [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            $this->autoReportError($e);
        });
    }

    /**
     * Automatically report errors to our error reporting system
     */
    private function autoReportError(Throwable $exception): void
    {
        Log::info('autoReportError called', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage()
        ]);
        // Skip if error reporting is disabled
        if (!config('app.auto_error_reporting', true)) {
            Log::info('Auto error reporting is disabled');
            return;
        }

        $errorReportService = app(ErrorReportService::class);

        // Skip certain types of exceptions
        if ($errorReportService->shouldSkipAutoReporting($exception)) {
            return;
        }

        try {
            // Determine if this should be auto-reported based on severity
            $shouldAutoReport = $errorReportService->shouldAutoReport($exception);

            if ($shouldAutoReport) {
                $errorReportService->reportException($exception, null, 'auto_reported');
            }
        } catch (Exception $e) {
            // Don't let error reporting break the application
            Log::error('Failed to auto-report error: ' . $e->getMessage());
        }
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // For API requests, always return JSON
        if ($request->expectsJson()) {
            return $this->renderJsonResponse($exception);
        }

        // For Filament requests, use custom error pages
        if ($request->is('admin/*') || $request->is('staff/*') ||  $request->is('filament/*')) {
            return $this->renderFilamentError($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Render JSON response for API errors
     */
    private function renderJsonResponse(Throwable $exception)
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

    /**
     * Render error for Filament admin panel
     */
    private function renderFilamentError($request, Throwable $exception)
    {
        // For development, show the detailed error
        if (config('app.debug')) {
            return parent::render($request, $exception);
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
