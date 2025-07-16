<?php

// config/error-reporting.php
return [

    /*
    |--------------------------------------------------------------------------
    | Auto Error Reporting
    |--------------------------------------------------------------------------
    |
    | This option controls whether errors should be automatically reported
    | to the error reporting system without user intervention.
    |
    */
    'auto_reporting' => env('AUTO_ERROR_REPORTING', true),

    /*
    |--------------------------------------------------------------------------
    | Error Reporting Environments
    |--------------------------------------------------------------------------
    |
    | Define which environments should have error reporting enabled.
    | By default, error reporting is disabled in testing.
    |
    */
    'enabled_environments' => [
        'production',
        'staging',
        'local',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Report Thresholds
    |--------------------------------------------------------------------------
    |
    | Define thresholds for when errors should be automatically reported.
    |
    */
    'thresholds' => [
        // Maximum number of duplicate errors to report per hour
        'max_duplicates_per_hour' => 10,

        // Minimum severity level to auto-report
        'min_severity' => 'medium', // low, medium, high, critical

        // Auto-report critical errors immediately
        'auto_report_critical' => true,

        // Auto-report high priority errors
        'auto_report_high' => true,

        // Auto-report medium priority errors
        'auto_report_medium' => true,

        // Auto-report low priority errors
        'auto_report_low' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Types to Skip
    |--------------------------------------------------------------------------
    |
    | Define which error types should not be auto-reported.
    |
    */
    'skip_auto_reporting' => [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
        // \Illuminate\Foundation\Http\Exceptions\MaintenanceModeException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure when and how to send notifications for errors.
    |
    */
    'notifications' => [
        // Send email notifications for critical errors
        'email_on_critical' => env('ERROR_EMAIL_CRITICAL', true),

        // Send Slack notifications for high priority errors
        'slack_on_high' => env('ERROR_SLACK_HIGH', false),

        // Email addresses to notify
        'email_recipients' => [
            env('ERROR_EMAIL_RECIPIENT', 'admin@example.com'),
        ],

        // Slack webhook URL
        'slack_webhook' => env('ERROR_SLACK_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Configure how long error reports should be kept.
    |
    */
    'retention' => [
        // Days to keep resolved errors
        'resolved_days' => 30,

        // Days to keep pending errors
        'pending_days' => 90,

        // Days to keep critical errors (longer retention)
        'critical_days' => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings for error reporting.
    |
    */
    'performance' => [
        // Queue error reporting to avoid blocking requests
        'queue_reporting' => env('QUEUE_ERROR_REPORTING', true),

        // Queue connection to use
        'queue_connection' => env('ERROR_QUEUE_CONNECTION', 'default'),

        // Maximum context data size (in bytes)
        'max_context_size' => 1024 * 10, // 10KB
    ],

];
