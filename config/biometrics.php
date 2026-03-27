<?php

return [
    'liveness_threshold' => env('BIOMETRIC_LIVENESS_THRESHOLD', 0.75),
    'confidence_threshold' => env('BIOMETRIC_CONFIDENCE_THRESHOLD', 0.80),
    'similarity_threshold' => env('BIOMETRIC_SIMILARITY_THRESHOLD', 0.85),
    'fallback_pin_enabled' => env('BIOMETRIC_FALLBACK_PIN', true),
    'max_enroll_attempts' => 3,
    'offline_ttl_hours' => 24,
    'fraud_window_seconds' => 120,
    'capacity_warn_pct' => 0.80,
    'consent_version' => '1.0',

    'providers' => [
        'rekognition' => \App\Biometrics\Providers\RekognitionProvider::class,
        'azure_face' => \App\Biometrics\Providers\AzureFaceProvider::class,
        'bioid' => \App\Biometrics\Providers\BioIDProvider::class,
        'on_premise' => \App\Biometrics\Providers\LocalModelProvider::class,
        'custom' => \App\Biometrics\Providers\CustomApiProvider::class,
    ],
];
