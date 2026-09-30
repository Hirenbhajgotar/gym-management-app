<?php

declare(strict_types=1);

return [
    'liveness_threshold' => env('BIOMETRIC_LIVENESS_THRESHOLD', 0.75),
    'confidence_threshold' => env('BIOMETRIC_CONFIDENCE_THRESHOLD', 0.80),
    'similarity_threshold' => env('BIOMETRIC_SIMILARITY_THRESHOLD', 0.85),
    'offline_ttl_hours' => 24,
    'consent_version' => '1.0',
    'heartbeat_timeout_minutes' => (int) env('BIOMETRIC_HEARTBEAT_TIMEOUT_MINUTES', 15),
    'heartbeat_consecutive_misses' => (int) env('BIOMETRIC_HEARTBEAT_CONSECUTIVE_MISSES', 1),
    'heartbeat_interval_seconds' => (int) env('BIOMETRIC_HEARTBEAT_INTERVAL_SECONDS', 60),

    'providers' => [
        'rekognition' => \App\Biometrics\Providers\RekognitionProvider::class,
        'azure_face' => \App\Biometrics\Providers\AzureFaceProvider::class,
        'bioid' => \App\Biometrics\Providers\BioIDProvider::class,
        'on_premise' => \App\Biometrics\Providers\LocalModelProvider::class,
        'custom' => \App\Biometrics\Providers\CustomApiProvider::class,
    ],

    // System-managed master credentials for cloud recognition providers
    // Gym owners do not configure these - they are provided and maintained at the platform level
    'providers_config' => [
        'rekognition' => [
            'region' => env('AWS_REKOGNITION_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'access_key' => env('AWS_REKOGNITION_KEY', env('AWS_ACCESS_KEY_ID', '')),
            'secret_key' => env('AWS_REKOGNITION_SECRET', env('AWS_SECRET_ACCESS_KEY', '')),
            'similarity_threshold' => (float) env('BIOMETRIC_SIMILARITY_THRESHOLD', 0.85) * 100,
        ],
        'azure_face' => [
            'endpoint' => env('AZURE_FACE_ENDPOINT', 'https://{location}.api.cognitive.microsoft.com'),
            'subscription_key' => env('AZURE_FACE_KEY', ''),
            'location' => env('AZURE_FACE_LOCATION', 'eastus'),
            'confidence_threshold' => (float) env('BIOMETRIC_CONFIDENCE_THRESHOLD', 0.80) * 100,
        ],
        'bioid' => [
            'wsdl_url' => env('BIOID_WSDL_URL', 'https://bws.bioid.com/extension'),
            'app_identifier' => env('BIOID_APP_ID', ''),
            'api_key' => env('BIOID_API_KEY', ''),
            'service_id' => env('BIOID_SERVICE_ID', '0'),
            'enable_liveness' => true,
        ],
    ],

    'biometric_consent_email_delay' => (int) env('BIOMETRIC_CONSENT_EMAIL_DELAY', 1), // minutes
    'default_unlock_duration' => (int) env('BIOMETRIC_DEFAULT_UNLOCK_DURATION', 5), // seconds
    'door_controller_timeout' => (int) env('BIOMETRIC_DOOR_CONTROLLER_TIMEOUT', env('DOOR_CONTROLLER_TIMEOUT', 3)), // seconds
    'trusted_domains' => env('BIOMETRIC_TRUSTED_DOMAINS') ? explode(',', (string) env('BIOMETRIC_TRUSTED_DOMAINS')) : [],
    'trusted_subnets' => env('BIOMETRIC_TRUSTED_SUBNETS') ? explode(',', (string) env('BIOMETRIC_TRUSTED_SUBNETS')) : [],
    'anti_passback_window_seconds' => (int) env('BIOMETRIC_ANTI_PASSBACK_WINDOW_SECONDS', 60),

    // ADMS (Automatic Device Management System) push protocol configuration
    'adms' => [
        'enabled' => env('ADMS_ENABLED', true),
        'require_comm_key' => env('ADMS_REQUIRE_COMM_KEY', true),
        'pbkdf2_iterations' => (int) env('ADMS_PBKDF2_ITERATIONS', 100000),
        'command_lease_seconds' => (int) env('ADMS_COMMAND_LEASE_SECONDS', 600),
        'command_retention_days' => (int) env('ADMS_COMMAND_RETENTION_DAYS', 90),
        'receipt_retention_days' => (int) env('ADMS_RECEIPT_RETENTION_DAYS', 30),
        'expired_member_grace_days' => (int) env('ADMS_EXPIRED_GRACE_DAYS', 10),
        'throttle' => env('ADMS_THROTTLE_LIMIT', '120,1'),
    ],
];
