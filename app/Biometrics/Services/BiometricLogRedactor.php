<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Enums\DefaultRole;
use App\Models\User;
use Throwable;

class BiometricLogRedactor
{
    /**
     * Keys whose values should always be redacted from logs.
     *
     * @var array<string>
     */
    protected const SENSITIVE_KEYS = [
        'pin',
        'card',
        'token',
        'secret',
        'password',
        'failure_reason',
        'failurereason',
        'error',
        'errors',
        'exception',
        'idempotency_key',
        'idempotencykey',
        'photo',
        'image',
        'data',
        'fingerprint',
        'employeenostring',
        'employee_no',
        'employeeno',
        'user_id',
        'userid',
        'member_id',
        'memberid',
        'gym_member_id',
        'gymmemberid',
        'template_data',
        'templatedata',
        'template_ref',
        'templateref',
        'raw_payload',
        'rawpayload',
        'auth_token',
        'authtoken',
        'pushcommkey',
        'access_token',
        'accesstoken',
        'app_secret',
        'appsecret',
        'webhook_verify_token',
        'webhookverifytoken',
        'api_key',
        'apikey',
        'authorization',
        'cookie',
        'set_cookie',
        'setcookie',
        'x_api_key',
        'xapikey',
        'proxy_authorization',
        'proxyauthorization',
        'bearer',
        'csrf_token',
        'csrftoken',
        'xsrf_token',
        'xsrftoken',
    ];

    /**
     * Allowed safe standard headers for logging/error reporting.
     * Everything else is redacted by default (deny-by-default allow-list).
     *
     * @var array<int, string>
     */
    protected const ALLOWED_HEADERS = [
        'accept',
        'accept-charset',
        'accept-encoding',
        'accept-language',
        'cache-control',
        'connection',
        'content-length',
        'content-type',
        'date',
        'dnt',
        'expect',
        'host',
        'if-match',
        'if-modified-since',
        'if-none-match',
        'if-range',
        'if-unmodified-since',
        'origin',
        'pragma',
        'sec-ch-ua',
        'sec-ch-ua-mobile',
        'sec-ch-ua-platform',
        'sec-fetch-dest',
        'sec-fetch-mode',
        'sec-fetch-site',
        'sec-fetch-user',
        'te',
        'upgrade-insecure-requests',
        'user-agent',
        'via',
        'x-forwarded-host',
        'x-forwarded-port',
        'x-forwarded-proto',
        'x-requested-with',
    ];

    /**
     * Explicit sensitive headers that must ALWAYS be redacted even if mistakenly allowed.
     *
     * @var array<int, string>
     */
    protected const DENIED_HEADERS = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'proxy-authorization',
        'x-csrf-token',
        'x-xsrf-token',
        'php-auth-pw',
        'php-auth-user',
        'x-pushcommkey',
        'x-device-token',
        'x-auth-token',
        'x-access-token',
        'x-signature',
    ];

    /**
     * Specifically redact or strip sensitive HTTP request/response headers.
     * Uses deny-by-default allow-list: only known harmless headers are preserved.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public static function redactHeaders(array $headers): array
    {
        $redacted = [];
        foreach ($headers as $name => $values) {
            $lower = strtolower($name);
            if (in_array($lower, self::DENIED_HEADERS, true) || ! in_array($lower, self::ALLOWED_HEADERS, true)) {
                $redacted[$name] = ['[REDACTED]'];
            } else {
                $redacted[$name] = is_array($values) ? array_values($values) : [$values];
            }
        }

        return $redacted;
    }

    /**
     * Redacts command payloads for presentation in Filament views and tables.
     * Hides raw technical failure_reason from unprivileged roles (only super_admin, admin, gym_owner, manager can view raw details).
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redactForPresentation(array $data, ?User $user = null): array
    {
        $redacted = self::redact($data);

        $isPrivileged = $user !== null && $user->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::ADMIN->value,
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
        ]);

        if (! $isPrivileged) {
            if (isset($redacted['failure_reason'])) {
                $redacted['failure_reason'] = '[REDACTED]';
            }

            if (isset($redacted['Name']) && is_string($redacted['Name'])) {
                $redacted['Name'] = self::mask($redacted['Name']);
            }

            if (isset($redacted['name']) && is_string($redacted['name'])) {
                $redacted['name'] = self::mask($redacted['name']);
            }
        }

        return $redacted;
    }

    /**
     * Recursively redacts sensitive biometric and credential keys from an array.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            $keyStr = (string) $key;
            $normalizedKey = strtolower(str_replace(['_', '-'], '', $keyStr));

            if ($value instanceof Throwable) {
                $redacted[$key] = '[EXCEPTION: ' . get_class($value) . ']';
            } elseif (self::isSensitiveKey($keyStr, $normalizedKey)) {
                $redacted[$key] = is_string($value) && strlen($value) > 4
                    ? substr($value, 0, 2) . '***' . substr($value, -2)
                    : '***';
            } elseif (is_array($value)) {
                $redacted[$key] = self::redact($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Mask a single string value (e.g. employee number, card number).
     */
    public static function mask(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
    }

    protected static function isSensitiveKey(string $keyStr, string $normalizedKey): bool
    {
        // 1. Exact match on normalized key
        if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        // 2. Token boundary match (splits snake_case, kebab-case, and camelCase)
        $tokens = preg_split('/(?=[A-Z])|[_\-\s]+/', $keyStr, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lowerTokens = array_map('strtolower', $tokens);

        foreach ($lowerTokens as $token) {
            if (in_array($token, self::SENSITIVE_KEYS, true)) {
                return true;
            }
        }

        // 3. Whole-word boundary match to prevent over-redaction like 'mapping' matching 'pin'
        $spacedKey = str_replace(['_', '-'], ' ', $keyStr);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (preg_match('/\b' . preg_quote($sensitive, '/') . '\b/i', $spacedKey)) {
                return true;
            }
        }

        return false;
    }
}
