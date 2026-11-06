<?php

declare(strict_types=1);

use App\Enums\AdmsUserTableEnum;

$admsUserTableEnv = env('ADMS_USER_TABLE');

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

    /**
     * Validity window, in days, of the signed biometric consent URL.
     *
     * The TTL is a security-relevant parameter: the signed URL is an
     * unauthenticated bearer credential that lets whoever holds it grant
     * biometric consent for the member. Every mint site and every
     * user-facing "valid for N days" string reads this single value via
     * App\Services\Biometric\BiometricConsentUrlFactory.
     *
     * Clamped to 1..30 days. The ceiling is a security bound, not a preference:
     * without it a misconfigured or tampered env value silently extends the
     * window in which a leaked link is still good for every member at once.
     */
    'consent_url_expiry_days' => min(30, max(1, (int) env('BIOMETRIC_CONSENT_URL_EXPIRY_DAYS', 7))),
    'default_unlock_duration' => (int) env('BIOMETRIC_DEFAULT_UNLOCK_DURATION', 5), // seconds
    'door_controller_timeout' => (int) env('BIOMETRIC_DOOR_CONTROLLER_TIMEOUT', env('DOOR_CONTROLLER_TIMEOUT', 3)), // seconds
    'trusted_domains' => env('BIOMETRIC_TRUSTED_DOMAINS') ? explode(',', (string) env('BIOMETRIC_TRUSTED_DOMAINS')) : [],
    'trusted_subnets' => env('BIOMETRIC_TRUSTED_SUBNETS') ? explode(',', (string) env('BIOMETRIC_TRUSTED_SUBNETS')) : [],
    'anti_passback_window_seconds' => (int) env('BIOMETRIC_ANTI_PASSBACK_WINDOW_SECONDS', 60),

    // ADMS (Automatic Device Management System) push protocol configuration
    'adms' => [
        'enabled' => filter_var(env('ADMS_ENABLED', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        'require_comm_key' => filter_var(env('ADMS_REQUIRE_COMM_KEY', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        'user_table' => AdmsUserTableEnum::resolve($admsUserTableEnv)->value,

        /*
         * Whether ADMS_USER_TABLE is unset or one of the recognised schemas.
         *
         * A blank (`ADMS_USER_TABLE=`) or misspelled value used to select the legacy
         * `user` schema by falling through an `??` chain, silently reintroducing the
         * hardware `-1004` failure the schema negotiation exists to remove. The value
         * above therefore resolves invalid input to the documented `USERINFO` default
         * instead of coercing it to `user`; this flag lets the production boot guard
         * report the misconfiguration once.
         */
        'user_table_is_valid' => $admsUserTableEnv === null
            || AdmsUserTableEnum::tryParse($admsUserTableEnv) !== null,
        'pbkdf2_iterations' => filter_var(env('ADMS_PBKDF2_ITERATIONS', 100000), FILTER_VALIDATE_INT, [
            'options' => ['default' => 100000, 'min_range' => 1],
        ]),

        /*
         * Verification floor. Fixed at the 100,000 constant in every
         * environment. tests/TestCase.php lowers it at runtime in the testing
         * environment only, so a production deployment cannot weaken stored
         * verifiers or raise the floor above an existing hash.
         */
        'pbkdf2_min_iterations' => \App\Models\BiometricDevice::MIN_PBKDF2_ITERATIONS,

        /**
         * Time-bounded compatibility switch for verifiers created before keys were
         * sanitized. Keep enabled for one release while whitespace-bearing legacy
         * devices rotate; disable once the fleet has re-provisioned, then remove.
         */
        'allow_legacy_unsanitized_comm_key' => filter_var(env('ADMS_ALLOW_LEGACY_UNSANITIZED_COMM_KEY', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,

        /**
         * Per-device online guessing controls. Failed pushcommkey attempts are
         * counted by device id (never by raw serial) and locked out temporarily
         * after the configured threshold.
         */
        'auth_max_failures' => filter_var(env('ADMS_AUTH_MAX_FAILURES', 5), FILTER_VALIDATE_INT, [
            'options' => ['default' => 5, 'min_range' => 1],
        ]),
        'auth_lockout_seconds' => filter_var(env('ADMS_AUTH_LOCKOUT_SECONDS', 900), FILTER_VALIDATE_INT, [
            'options' => ['default' => 900, 'min_range' => 1],
        ]),
        'command_lease_seconds' => (int) env('ADMS_COMMAND_LEASE_SECONDS', 600),
        'command_retention_days' => (int) env('ADMS_COMMAND_RETENTION_DAYS', 90),
        'receipt_retention_days' => (int) env('ADMS_RECEIPT_RETENTION_DAYS', 30),
        'expired_member_grace_days' => (int) env('ADMS_EXPIRED_GRACE_DAYS', 10),

        /**
         * Maximum hardware deliveries per command before it is considered exhausted.
         *
         * One "attempt" is one delivery to the terminal. Only `AdmsDeviceCommand::markSent()`
         * and the stale-lease re-lease in `leaseNext()` increment the counter, so the
         * device-answer path and the lease-timeout path both get exactly this many
         * deliveries. Changing this value changes the retry budget for every writer.
         */
        'max_attempts' => (int) env('ADMS_MAX_ATTEMPTS', 3),

        /**
         * Hours an exhausted `user_delete` is left alone before the hourly reconciler
         * will re-drive it. Prevents a deterministic firmware fault from producing
         * hardware writes every run, while keeping the obligation self-healing.
         */
        'retry_cooldown_hours' => (int) env('ADMS_RETRY_COOLDOWN_HOURS', 24),

        /**
         * Vendor `user_delete` return codes treated as "user already absent from terminal".
         *
         * These converge to ACKNOWLEDGED because the terminal no longer holds the credential,
         * which satisfies the GDPR Art. 17 erasure obligation. ZKTeco and eSSL firmware use
         * different conventions, so the list is operator-configurable. It ships EMPTY by
         * default: a code that is not the terminal's actual "absent" convention would convert
         * a failed delete into a fabricated erasure, so the safe default is to treat every
         * non-zero code as a failure and require an operator to opt their firmware in. Every
         * convergence decision is logged with the raw code by `markAcknowledged()`.
         *
         * @var array<int, string>
         */
        'deletion_convergence_codes' => array_values(array_filter(
            array_map(
                'strval',
                explode(',', (string) env('ADMS_DELETION_CONVERGENCE_CODES', ''))
            ),
            fn (string $code): bool => $code !== ''
        )),
    ],

    /**
     * Cooldown before a permanently-refusing cloud provider reaches the terminal state.
     *
     * The budget is measured in TIME, not in retries. Every writer shares one erasure
     * obligation — the hourly reconciler and the consent-revoked queue listener (`$tries = 3`
     * with a 60/180/300s backoff) — so a raw retry count lets the listener exhaust the whole
     * budget in about four minutes and drive a transient provider outage terminal, while the
     * member's own erasure is reported complete and no automated retry is left armed. Within
     * the cooldown a failing provider always stays retryable; once it has been failing for
     * longer than this, the row reaches the terminal `DeletionUnresolvable` state and needs an
     * operator.
     */
    'cloud_erasure_cooldown_hours' => max(1, (int) env('BIOMETRIC_CLOUD_ERASURE_COOLDOWN_HOURS', 24)),

    'device_throttle' => filter_var(env('BIOMETRIC_DEVICE_THROTTLE', 120), FILTER_VALIDATE_INT, ['options' => ['default' => 120, 'min_range' => 0]]),
];
