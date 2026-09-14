<?php

declare(strict_types=1);

use App\Http\Middleware\ErrorContextMiddleware;
use App\Http\Middleware\ExportDownloadMiddleware;
use App\Http\Middleware\TeamsPermission;
use App\Services\ErrorReportService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

$isTestingMode = (isset($_SERVER['ARGV']) && is_array($_SERVER['ARGV']) && in_array('--env=testing', $_SERVER['ARGV'], true))
    || getenv('APP_ENV') === 'testing'
    || (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing')
    || (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'testing');

if ($isTestingMode) {
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = 'testing';
    $_SERVER['APP_ENV'] = 'testing';

    putenv('DB_DATABASE=gym_app_test');
    $_ENV['DB_DATABASE'] = 'gym_app_test';
    $_SERVER['DB_DATABASE'] = 'gym_app_test';
}

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        api: __DIR__ . '/../routes/api.php',

        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: [
                '127.0.0.1',
                '::1',
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->alias([
            'auth:biometric-device' => \App\Http\Middleware\AuthenticateBiometricDevice::class,
        ]);

        // $middleware->alias([
        //     'set.permissions.team' => TeamsPermission::class,
        // ]);

        $middleware->web([
            TeamsPermission::class, // Add custom middleware here
            ErrorContextMiddleware::class,
            ExportDownloadMiddleware::class,
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
            if (! config('error-reporting.auto_reporting', true)) {
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
                Log::info(
                    'Should auto report: ' . ($shouldAutoReport ? 'yes' : 'no') . ' Error: ' . $e->getMessage() . ' File: ' . $e->getFile() . ':' . $e->getLine(),
                );

                if ($shouldAutoReport) {
                    $errorReport = $errorReportService->reportException(
                        $e,
                        null,
                        'auto_reported',
                    );
                    Log::info('Error report created via withExceptions', [
                        'error_report_id' => $errorReport->id ?? 'null',
                        'error_hash' => $errorReport->error_hash ?? 'null',
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
        $exceptions->render(function (Throwable $e, Request $request) {
            // Tampered or otherwise invalid Livewire payload — reject as a 400
            // instead of surfacing a 500. This is an attacker probe / corrupted
            // client state, not an application bug, so we don't want a stack trace.
            // The same applies to calls on non-existent component methods (e.g. the
            // legacy Livewire v2 "emit" method) which Livewire rejects with a
            // MethodNotFoundException.
            if ($e instanceof CorruptComponentPayloadException || $e instanceof MethodNotFoundException) {
                if ($request->expectsJson() || $request->header('X-Livewire')) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Invalid request payload.',
                        'status' => 400,
                        'timestamp' => now()->toISOString(),
                    ], 400);
                }

                abort(400);
            }

            $errorReportService = app(ErrorReportService::class);

            // For API requests, always return JSON
            if ($request->expectsJson()) {
                return $errorReportService->renderJsonResponse($e);
            }

            // For Filament requests, use custom error pages
            if (
                $request->is('admin/*') ||
                $request->is('staff/*') ||
                $request->is('filament/*')
            ) {
                return $errorReportService->renderFilamentError($request, $e);
            }

            // Return null to let Laravel handle it normally
            return null;
        });

        // // You can also add specific exception handling
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
    ->create();

$app->booted(function (Application $app): void {
    if ($app->environment('testing')) {
        $db = $app->make('config')->get('database.connections.mysql.database');

        if ($db === 'gym_app') {
            throw new RuntimeException('SAFETY VIOLATION: Application is in testing mode but database connection resolves to "gym_app". Aborting.');
        }
    }
});

return $app;
