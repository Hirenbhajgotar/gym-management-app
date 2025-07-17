<?php

use App\Console\Commands\CleanResolvedErrorReportsCommand;
use App\Http\Middleware\ErrorContextMiddleware;
use App\Http\Middleware\TeamsPermission;
use App\Services\ErrorReportService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // $middleware->alias([
        //     'set.permissions.team' => TeamsPermission::class,
        // ]);

        $middleware->web([
            TeamsPermission::class, // Add custom middleware here
            ErrorContextMiddleware::class,
        ]);

        $middleware->priority([
            SubstituteBindings::class,

            TeamsPermission::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception reporting
        $exceptions->report(function (Throwable $e) {
            // Skip if error reporting is disabled
            if (!config('error-reporting.auto_reporting', true)) {
                return;
            }

            try {
                $errorReportService = app(ErrorReportService::class);

                // Skip certain types of exceptions
                if ($errorReportService->shouldSkipAutoReporting($e)) {
                    Log::info('Skipping auto reporting for this exception');
                    return;
                }

                // Determine if this should be auto-reported based on severity
                $shouldAutoReport = $errorReportService->shouldAutoReport($e);
                Log::info('Should auto report: ' . ($shouldAutoReport ? 'yes' : 'no'));

                if ($shouldAutoReport) {
                    $errorReport = $errorReportService->reportException($e, null, 'auto_reported');
                    Log::info('Error report created via withExceptions', [
                        'error_report_id' => $errorReport->id ?? 'null',
                        'error_hash' => $errorReport->error_hash ?? 'null'
                    ]);
                }
            } catch (Exception $exception) {
                Log::error('Failed to auto-report error via withExceptions', [
                    'original_exception' => $e,
                    'reporting_exception' => $exception,
                ]);
            }
        });

        // Custom exception rendering
        $exceptions->render(function (Throwable $e, $request) {
            $errorReportService = app(ErrorReportService::class);

            // For API requests, always return JSON
            if ($request->expectsJson()) {
                return $errorReportService->renderJsonResponse($e);
            }

            // For Filament requests, use custom error pages
            if ($request->is('admin/*') || $request->is('staff/*') || $request->is('filament/*')) {
                return $errorReportService->renderFilamentError($request, $e);
            }

            // Return null to let Laravel handle it normally
            return null;
        });

        // You can also add specific exception handling
        // $exceptions->renderable(function (ValidationException $e, $request) {
        //     if ($request->expectsJson()) {
        //         return response()->json([
        //             'error' => true,
        //             'message' => 'Validation failed',
        //             'errors' => $e->errors(),
        //             'status' => 422,
        //             'timestamp' => now()->toISOString(),
        //         ], 422);
        //     }
        // });
    })
    ->withCommands([
        // CleanResolvedErrorReportsCommand::class
    ])
    ->create();
