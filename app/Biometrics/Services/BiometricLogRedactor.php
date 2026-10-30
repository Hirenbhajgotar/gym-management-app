<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Enums\DefaultRole;
use App\Models\User;
use stdClass;
use Throwable;

/**
 * Redaction helpers for biometric log lines and operator-facing output.
 *
 * The boundary is correlation, not anonymisation: {@see self::mask()} deliberately reveals the
 * first and last two characters so an operator can match a row across log lines, and for a
 * device-native row `template_ref` IS the member id. Masking such a value therefore narrows the
 * reference without removing it, and must not be relied on to keep a credential reference out
 * of a log.
 */
class BiometricLogRedactor
{
    /**
     * Upper bound for free-text redaction. Larger inputs are truncated and
     * flagged so a pathological payload cannot run synchronously forever.
     */
    public const MAX_TEXT_BYTES = 262144;

    /**
     * Total byte budget across all string leaves in one request payload.
     * Individual leaves are also capped, but a payload with thousands of
     * moderately sized fields can still be expensive to redact synchronously.
     */
    public const MAX_REQUEST_BYTES = 1048576;

    /**
     * Maximum number of string leaves processed from one request payload.
     * The byte budget alone still allows thousands of full redaction passes.
     */
    public const MAX_REQUEST_LEAVES = 2000;

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
        'commkey',
        'admscommkey',
        'admscommkeyhash',
        'admscommkeysalt',
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
     * Query/text names that carry credentials or biometric PII. Shared by URL
     * redaction and the free-text redactor so error messages cannot use a
     * narrower list.
     *
     * @var array<int, string>
     */
    public const SENSITIVE_NAMES = [
        'pushcommkey',
        'push_comm_key',
        'commkey',
        'comm_key',
        'adms_comm_key',
        'adms_comm_key_hash',
        'adms_comm_key_salt',
        'token',
        'auth_token',
        'access_token',
        'refresh_token',
        'device_token',
        'api_token',
        'password',
        'secret',
        'api_key',
        'apikey',
        'app_secret',
        'client_secret',
        'webhook_secret',
        'webhook_verify_token',
        'signature',
        'x_signature',
        'authorization',
        'proxy_authorization',
        'php_auth_pw',
        'php_auth_user',
        'pwd',
        'passwd',
        'cookie',
        'set_cookie',
        'csrf_token',
        'xsrf_token',
        'bearer',
        'private_key',
        'pin',
        'card',
        'photo',
        'image',
        'template_data',
        'template_ref',
        'raw_payload',
        'failure_reason',
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
        'x-comm-key',
        'x-adms-comm-key',
        'x-device-token',
        'x-auth-token',
        'x-access-token',
        'x-signature',
    ];

    /**
     * Keys that are containers rather than scalar credentials. Structured
     * redaction recurses into them so benign siblings survive.
     *
     * @var array<int, string>
     */
    private const CONTAINER_KEYS = ['data', 'requestdata'];

    /**
     * Specifically redact or strip sensitive HTTP request/response headers.
     * Uses deny-by-default allow-list: only known harmless headers are preserved.
     *
     * @param  array<array-key, mixed>  $headers
     * @return array<array-key, mixed>
     */
    public static function redactHeaders(array $headers): array
    {
        $redacted = [];
        foreach ($headers as $name => $values) {
            if (! is_string($name)) {
                $entries = is_array($values) ? array_values($values) : [$values];
                $redacted[$name] = array_map(
                    static fn (mixed $value): mixed => self::redactNumericHeaderEntry($value),
                    $entries
                );

                continue;
            }

            $lower = strtolower($name);
            if (in_array($lower, self::DENIED_HEADERS, true) || ! in_array($lower, self::ALLOWED_HEADERS, true)) {
                $redacted[$name] = ['[REDACTED]'];

                continue;
            }

            $allowed = is_array($values) ? array_values($values) : [$values];

            // Even an allow-listed header value can carry a credential (a
            // querystring in Origin/Referer or a token in User-Agent), so run
            // every value through the free-text and URL redactors.
            $redacted[$name] = array_map(
                static fn (mixed $value): mixed => is_string($value)
                    ? self::redactText(self::redactUrl($value))
                    : $value,
                $allowed
            );
        }

        return $redacted;
    }

    /**
     * Historical header lists can be numeric-indexed `["Name: value"]` entries
     * rather than an associative map. Parse each entry and apply the same
     * deny-by-default allow-list; an unparseable entry is redacted as free
     * text rather than passed through.
     */
    private static function redactNumericHeaderEntry(mixed $entry): mixed
    {
        if (! is_string($entry)) {
            return $entry;
        }

        if (preg_match('/^(?P<name>[^:\r\n]+):[ \t]?(?P<value>.*)$/', $entry, $matches) !== 1) {
            return self::redactText($entry);
        }

        $headers = self::redactHeaders([$matches['name'] => [$matches['value']]]);
        $values = $headers[$matches['name']] ?? ['[REDACTED]'];

        return $matches['name'] . ': ' . implode(', ', is_array($values) ? $values : [$values]);
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
     * Redact credential-looking values from free text (exception messages,
     * user descriptions, previous-exception text, stack traces).
     *
     * Handles `key=value`, `key: value`, JSON `"key":"value"`, PHP array dumps
     * (`'key' => 'value'`, `print_r()`/`var_dump()` output), `$_GET['key']`,
     * space-separated human labels, `Basic`/`Digest`/`Bearer` authorization
     * schemes, header prefixes (`X-PushCommKey`), array suffixes
     * (`pushcommkey[0]`), percent-encoded names (up to three layers), escaped
     * JSON strings and bare escaped JSON objects.
     *
     * Only credential/PII names are matched, so benign diagnostics such as
     * `Error:`, `Exception:`, and `data:` are never corrupted. A non-sensitive
     * assignment does not consume its value, so a secret later on the same
     * line is still examined. The scan is linear in the input length.
     *
     * Line breaks, tabs, and benign text (for example `1+2` or `100%25`) are
     * preserved. Callers that embed the result in a single Markdown line must
     * collapse it themselves.
     */
    public static function redactText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $truncated = false;

        // Bound the input so a pathological payload cannot turn error
        // reporting into an unbounded synchronous operation. The tail is
        // dropped rather than left unredacted.
        if (strlen($text) > self::MAX_TEXT_BYTES) {
            $text = mb_strcut($text, 0, self::MAX_TEXT_BYTES, 'UTF-8');
            $truncated = true;
        }

        // A cheap prefilter keeps benign 200 KB diagnostics off the regex
        // pipeline. It is deliberately conservative: any encoded form that
        // might hide a sensitive name still goes through the full scan.
        if (! self::hasRedactionCandidate($text)) {
            return self::finalizeRedactedText($text, $truncated);
        }

        $jsonDocument = self::redactJsonText($text);

        if ($jsonDocument !== null) {
            // A valid JSON document was decoded and leaf-redacted already.
            // Running the free-text scanners over its escaped strings would
            // mistake `\"name\"` for an assignment and corrupt the structure.
            $value = $jsonDocument;
        } else {
            $value = self::redactEmbeddedJsonStrings($text);
            $value = self::redactEmbeddedJsonDocuments($value);
            $value = self::redactEscapedJson($value);
            $value = self::redactBareEscapedAssignments($value);
            $value = self::redactSerializedValues($value);
            $value = self::redactSeparatedSensitiveLabels($value);
            $value = self::redactAuthorizationSchemes($value);
            $value = self::redactSensitiveAssignments($value);
            $value = self::redactEncodedAssignments($value);
            $value = self::redactUrlParametersInText($value);
            $value = self::redactSensitiveCallArguments($value);
            $value = self::redactRouteTokens($value);
            $value = self::redactUrlUserInfo($value);
            $value = self::redactBareTokens($value);
        }

        return self::finalizeRedactedText($value, $truncated);
    }

    private static function finalizeRedactedText(string $value, bool $truncated): string
    {
        if ($truncated) {
            $value .= "\n[TRUNCATED]";
        }

        // Preserve tab, CR, and LF; drop every other C0 control character and
        // DEL so the text cannot inject terminal control sequences.
        $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $value);

        // Fail closed: never return the unredacted input when PCRE gives up.
        if ($stripped === null || preg_last_error() !== PREG_NO_ERROR) {
            return '[REDACTION FAILED]';
        }

        return $stripped;
    }

    /**
     * Cheap candidate test used before the full redaction pipeline.
     */
    private static function hasRedactionCandidate(string $text): bool
    {
        $probe = $text;

        for ($i = 0; $i < 3; $i++) {
            if (self::containsSensitiveName($probe)
                || self::containsStructuralJsonKey($probe)
                || stripos($probe, 'bearer') !== false
                || stripos($probe, 'basic') !== false
                || stripos($probe, 'digest') !== false
                || str_contains($probe, '://')
                || stripos($probe, 'webhook') !== false
                || str_contains($probe, '\\u')
                || preg_match('/(?<![A-Za-z0-9_])(?:a:(?:-?\d+|[A-Za-z]+):\{|O:(?:-?\d+|[A-Za-z]+):"|s:(?:-?\d+|[A-Za-z]+):")/', $probe) === 1
                || preg_match('/(?<![A-Fa-f0-9])[A-Fa-f0-9]{48,64}(?![A-Fa-f0-9])/', $probe) === 1) {
                return true;
            }

            if (! str_contains($probe, '%') && ! str_contains($probe, '+')) {
                break;
            }

            $next = urldecode($probe);

            if ($next === $probe) {
                break;
            }

            $probe = $next;
        }

        return false;
    }

    /**
     * Apply the free-text and URL redactors to every string leaf of an array.
     * Used for read-time display of historical error-report data.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redactStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::redactStrings($value);
            } elseif (is_string($value)) {
                // redactUrl() strips control characters. Only use it for a
                // single-line URL leaf; multiline diagnostics must retain
                // their line structure.
                $data[$key] = strpbrk($value, "\r\n\t") === false
                    ? self::redactText(self::redactUrl($value))
                    : self::redactText($value);
            }
        }

        return $data;
    }

    /**
     * Redact a whole valid JSON document while preserving its structure.
     * Returns null when the text is not a JSON object/array.
     *
     * Objects stay objects (including numeric keys and `{}`), integers
     * outside the platform range stay strings, and floats keep their
     * fraction. Generic diagnostic keys (`error`, `errors`, `exception`)
     * are leaf-redacted so a benign prefix survives instead of collapsing
     * the whole value to a placeholder.
     */
    public static function redactJsonText(string $text): ?string
    {
        $trimmed = ltrim($text);

        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return null;
        }

        try {
            $decoded = json_decode($text, false, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($decoded) && ! $decoded instanceof stdClass) {
            return null;
        }

        return self::encodeRedactedJson(self::redactJsonNode($decoded));
    }

    private static function redactJsonNode(mixed $node, ?string $key = null): mixed
    {
        if ($key !== null && self::jsonKeyIsStructurallySensitive($key)) {
            $normalizedKey = strtolower(str_replace(['_', '-'], '', $key));
            $isContainer = in_array($normalizedKey, self::CONTAINER_KEYS, true)
                && (is_array($node) || $node instanceof stdClass || (is_string($node) && self::looksLikeJson($node)));

            if (! $isContainer) {
                return '***';
            }
        }

        if ($node instanceof stdClass) {
            $redacted = new stdClass();

            /** @var array<array-key, mixed> $properties */
            $properties = get_object_vars($node);

            foreach ($properties as $property => $value) {
                $propertyName = (string) $property;
                $redacted->{$propertyName} = self::redactJsonNode($value, $propertyName);
            }

            return $redacted;
        }

        if (is_array($node)) {
            $redacted = [];

            foreach ($node as $index => $value) {
                $redacted[$index] = self::redactJsonNode($value);
            }

            return $redacted;
        }

        if (is_string($node)) {
            return self::redactText($node);
        }

        return $node;
    }

    private static function jsonKeyIsStructurallySensitive(string $key): bool
    {
        $normalizedKey = strtolower(str_replace(['_', '-'], '', $key));

        if (in_array($normalizedKey, ['error', 'errors', 'exception'], true)) {
            return false;
        }

        return self::isSensitiveKey($key, $normalizedKey);
    }

    private static function encodeRedactedJson(mixed $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_PRESERVE_ZERO_FRACTION | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($encoded) ? $encoded : '[REDACTED]';
    }

    /**
     * Redact balanced JSON objects/arrays embedded in prose.
     *
     * A whole-document JSON value is handled by {@see self::redactJsonText()}.
     * An exception message or support note may prefix it (`Exception: {"data":
     * "749201"}`); this pass finds the balanced container, structurally
     * redacts it, and re-encodes it without touching the surrounding text.
     *
     * The scan is bounded: at most 64 containers are processed, and the
     * remainder is truncated rather than left unredacted when the budget is
     * exhausted.
     */
    private static function redactEmbeddedJsonDocuments(string $value): string
    {
        $length = strlen($value);
        $result = '';
        $offset = 0;
        $attempts = 0;

        while ($offset < $length) {
            $found = strcspn($value, '{[', $offset);

            if ($offset + $found >= $length) {
                $result .= substr($value, $offset);

                break;
            }

            $start = $offset + $found;

            if ($attempts >= 64) {
                return $result . substr($value, $offset, $start - $offset) . '[TRUNCATED]';
            }

            $lookahead = substr($value, $start + 1, 8);

            if (preg_match('/^\s*"/', $lookahead) !== 1) {
                $result .= substr($value, $offset, $start + 1 - $offset);
                $offset = $start + 1;

                continue;
            }

            $attempts++;
            $end = self::findBalancedJsonEnd($value, $start);

            if ($end === null) {
                $result .= substr($value, $offset, $start + 1 - $offset);
                $offset = $start + 1;

                continue;
            }

            $candidate = substr($value, $start, $end - $start + 1);
            $redacted = self::redactJsonText($candidate);

            $result .= substr($value, $offset, $start - $offset);

            if (is_string($redacted) && $redacted !== $candidate) {
                $result .= $redacted;
            } else {
                $result .= $candidate;
            }

            $offset = $end + 1;
        }

        return $result;
    }

    /**
     * Redact a value that is embedded as a JSON string (including the escaped
     * form found in historical Livewire snapshots) while keeping valid JSON.
     */
    private static function redactEmbeddedJsonStrings(string $text): string
    {
        $result = '';
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            $start = strpos($text, '"', $offset);

            if ($start === false) {
                $result .= substr($text, $offset);

                break;
            }

            $end = self::quotedValueEnd($text, $start, '"');

            if ($end === null) {
                $result .= substr($text, $offset);

                break;
            }

            $result .= substr($text, $offset, $start + 1 - $offset);
            $result .= self::redactEmbeddedJsonInner(substr($text, $start + 1, $end - $start - 1));
            $result .= '"';
            $offset = $end + 1;
        }

        return $result;
    }

    private static function redactEmbeddedJsonInner(string $inner): string
    {
        if ($inner === '' || ! str_contains($inner, '\\')) {
            return $inner;
        }

        $unescaped = self::unescapeJsonBody($inner);
        $trimmed = ltrim($unescaped);

        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return $inner;
        }

        try {
            $decoded = json_decode($unescaped, false, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (Throwable) {
            // A malformed/truncated escaped snapshot must not fall through
            // unredacted. Only fail the field open when it carries no name we
            // would have redacted anyway.
            return self::containsSensitiveName($inner) ? '[REDACTED]' : $inner;
        }

        if (! is_array($decoded) && ! $decoded instanceof stdClass) {
            return $inner;
        }

        $json = self::encodeRedactedJson(self::redactJsonNode($decoded));

        if ($json === '[REDACTED]') {
            return '[REDACTED]';
        }

        // Escape the inner JSON so it can be embedded in the quoted string.
        return self::escapeJsonForEmbedding($json);
    }

    /**
     * Redact bare escaped JSON objects such as
     * `payload: {\"pushcommkey\":\"123456\"}` that are not wrapped in quotes.
     */
    private static function redactEscapedJson(string $text): string
    {
        $result = '';
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length && preg_match('/[\[{](?=\\\\")/', $text, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $matches[0][1];
            $end = self::findBalancedEscapedJsonEnd($text, $start);

            if ($end === null) {
                $tail = substr($text, $start);

                if (self::containsSensitiveName($tail)) {
                    $result .= substr($text, $offset, $start - $offset) . '[REDACTED]';
                    $offset = $length;

                    break;
                }

                $result .= substr($text, $offset);
                $offset = $length;

                break;
            }

            $raw = substr($text, $start, $end - $start + 1);
            $encoded = null;

            try {
                $decoded = json_decode(self::unescapeJsonBody($raw), false, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);

                if (is_array($decoded) || $decoded instanceof stdClass) {
                    $encoded = self::encodeRedactedJson(self::redactJsonNode($decoded));
                }
            } catch (Throwable) {
                // Fall through to the fail-closed branch below.
            }

            $result .= substr($text, $offset, $start - $offset);

            if (is_string($encoded)) {
                $result .= self::escapeJsonForEmbedding($encoded);
            } elseif (self::containsSensitiveName($raw)) {
                $result .= '[REDACTED]';
            } else {
                $result .= $raw;
            }

            $offset = $end + 1;
        }

        return $result . substr($text, $offset);
    }

    /**
     * Redact bare escaped JSON pairs without surrounding braces, such as
     * `\"pushcommkey\":\"123456\"`.
     */
    private static function redactBareEscapedAssignments(string $text): string
    {
        $valuePattern = '/\\\\"(?P<name>[^"\\\\]+)\\\\"\s*(?P<delimiter>:|=)\s*\\\\"(?P<value>[^"\\\\]*)\\\\"/';

        $redacted = preg_replace_callback(
            $valuePattern,
            static function (array $matches): string {
                if (! self::isSensitiveName(stripcslashes($matches['name']))) {
                    return $matches[0];
                }

                return '\\"' . $matches['name'] . '\\"' . $matches['delimiter'] . '\\"***\\"';
            },
            $text
        );

        if ($redacted === null) {
            return '[REDACTION FAILED]';
        }

        // Truncated/malformed bare pairs must not leak the tail either. A
        // complete pair contains both opening and closing `\"` markers and was
        // already redacted above; fewer than two means the value is unfinished.
        $truncated = preg_replace_callback(
            '/(?<!\\\\)\\\\"(?P<name>[^"\\\\]+)\\\\"\s*(?P<delimiter>:|=)\s*(?P<tail>.*)$/m',
            static function (array $matches): string {
                if (! self::isSensitiveName(stripcslashes($matches['name']))
                    || substr_count($matches['tail'], '\\"') >= 2) {
                    return $matches[0];
                }

                return '\\"' . $matches['name'] . '\\"' . $matches['delimiter'] . '\\"***\\"';
            },
            $redacted
        );

        return $truncated ?? '[REDACTION FAILED]';
    }

    private static function escapeJsonForEmbedding(string $json): string
    {
        $encoded = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($encoded) || strlen($encoded) < 2) {
            return '[REDACTED]';
        }

        return substr($encoded, 1, -1);
    }

    private static function unescapeJsonBody(string $value): string
    {
        return str_replace(['\\"', '\\\\', '\\/'], ['"', '\\', '/'], $value);
    }

    /**
     * Redact a sensitive label separated from its value by whitespace,
     * punctuation, or a newline (`pushcommkey 749201`, `Push Comm Key\n749201`,
     * `Pins 1234`).
     *
     * Only the immediately following token/quoted string is consumed, and only
     * when it looks like a credential or biometric PII. This keeps benign
     * diagnostics (`token expired at 12:30`, `The pin pad is offline`) intact.
     */
    private static function redactSeparatedSensitiveLabels(string $value): string
    {
        // A single-token assignment with a pathological digit-group value is
        // handled linearly by the assignment scanner. Running the label
        // pattern over it can hit PCRE's match limit (the failure depends on
        // JIT/match-limit state), so skip straight to the scanner.
        if (strlen($value) > 4096
            && preg_match('/(?<![A-Za-z0-9_])(?:push[_\-]?comm[_\-]?key|comm[_\-]?key|adms[_\-]?comm[_\-]?key)\s*[=:]\s*\d[\d, \t]{4096,}/i', $value) === 1) {
            return $value;
        }

        /** @var string|null $pattern */
        static $pattern = null;

        if ($pattern === null) {
            $labels = [];

            foreach (self::SENSITIVE_NAMES as $name) {
                $words = array_map(
                    static fn (string $word): string => preg_quote($word, '/'),
                    explode('_', $name)
                );

                $labels[] = implode('[\s_\-.,;\/]+', $words) . 's?';
                $labels[] = implode('', $words) . 's?';
            }

            $labels = array_values(array_unique($labels));
            usort($labels, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

            $gapUnit = '(?:[\s_\-.,;\/]|%20|%09|%2C|%2F|%3B|%5F|%2E|\+)';
            $typeAnnotation = '(?:string|int|integer|float|double|bool|boolean|array|object)\b\s*(?:\(\s*-?\d*\s*\))?\s*';

            $pattern = '/(?<![A-Za-z0-9_])(?<label>' . implode('|', $labels) . ')'
                . '(?<gap>(?:' . $gapUnit . '+|[:=])' . $gapUnit . '*[:=]?' . $gapUnit . '*)'
                . '(?<value>'
                . '"(?:\\\\.|[^"\\\\\r\n])*"'
                . '|\'(?:\\\\.|[^\'\\\\\r\n])*\''
                . '|' . $typeAnnotation . '(?:"(?:\\\\.|[^"\\\\\r\n])*"|\'(?:\\\\.|[^\'\\\\\r\n])*\')'
                . '|(?>\d+(?:(?:[ \t]+|,)[ \t]*\d+)+)'
                . '|(?>\d+(?:[.,\-\/]\d+)+)'
                . '|[A-Za-z0-9]+(?:[.\-\/][A-Za-z0-9]+)*'
                . '|[^\s,;)\]}"\']+'
                . ')/i';
        }

        $redacted = preg_replace_callback(
            $pattern,
            static function (array $matches): string {
                $candidate = $matches['value'];

                // An explicit `=`/`:` assignment is handled by the assignment
                // scanner for single-token names. Only human multi-word labels
                // (`Push Comm Key: 123456`) need this pass, because the
                // scanner sees only the final lexical token (`Key`).
                if ((str_contains($matches['gap'], ':') || str_contains($matches['gap'], '='))
                    && preg_match('/\s/', $matches['label']) !== 1) {
                    return $matches[0];
                }

                if (! self::separatedValueLooksSensitive($matches['label'], $candidate)) {
                    return $matches[0];
                }

                $quote = $candidate[0] ?? '';

                if ($quote === '"' || $quote === "'") {
                    return $matches['label'] . $matches['gap'] . $quote . '[REDACTED]' . $quote;
                }

                return $matches['label'] . $matches['gap'] . '[REDACTED]';
            },
            $value
        );

        return $redacted ?? '[REDACTION FAILED]';
    }

    private static function separatedValueLooksSensitive(string $label, string $candidate): bool
    {
        $trimmed = trim($candidate);

        if ($trimmed === '' || str_contains($trimmed, '[REDACTED]') || str_contains($trimmed, '[REDACTED')) {
            return false;
        }

        // Var-dump type annotations (`string(6) "..."`, `array(1) {...}`) and
        // structured values are consumed by the assignment scanner, which
        // understands their full extent. Handling only the fragment here would
        // leave the real value behind.
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return false;
        }

        // A var_dump annotation may wrap the real value: `string(6) "749201"`.
        // Inspect the quoted inner value instead of skipping the whole token.
        if (preg_match('/^(?:string|int|integer|float|double|bool|boolean|array|object)\b\s*(?:\(\s*-?\d*\s*\))?\s*(?<inner>"(?:\\\\.|[^"\\\\\r\n])*"|\'(?:\\\\.|[^\'\\\\\r\n])*\')$/i', $trimmed, $typed) === 1) {
            return self::separatedValueLooksSensitive($label, $typed['inner']);
        }

        if (preg_match('/^(?:string|int|integer|float|double|bool|boolean|array|object)\b\s*(?:\(\s*\d*\s*\)?)?$/i', $trimmed) === 1) {
            return false;
        }

        $quoted = false;

        if (strlen($trimmed) >= 2
            && (($trimmed[0] === '"' && str_ends_with($trimmed, '"'))
                || ($trimmed[0] === "'" && str_ends_with($trimmed, "'")))) {
            $quoted = true;
            $trimmed = substr($trimmed, 1, -1);
        }

        // A following `word=`/`word:` is another assignment, not this label's
        // value. This keeps multi-line diagnostics and `token=abc user=bob`
        // from swallowing later fields.
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*\s*[:=]/', $trimmed) === 1) {
            return false;
        }

        $digits = preg_match_all('/\d/', $trimmed);

        if ($digits === false) {
            return false;
        }

        $kind = self::separatedLabelKind($label);

        // The form and helper accept six alphanumeric characters. An
        // all-letter key has no digit, so the generic credential gate below
        // would let it through.
        if (self::separatedLabelIsCommKey($label)
            && preg_match('/^[A-Za-z0-9]{6}$/', $trimmed) === 1) {
            return true;
        }

        if ($kind === 'pin_card') {
            return $digits >= 3;
        }

        if ($quoted) {
            return true;
        }

        if ($kind === 'pii') {
            return strlen($trimmed) >= 8;
        }

        return $digits > 0
            || strlen($trimmed) >= 16
            || preg_match('/^[A-Fa-f0-9]{32,}$/', $trimmed) === 1
            || preg_match('/^[A-Za-z0-9+\/=_-]{32,}$/', $trimmed) === 1;
    }

    private static function separatedLabelKind(string $label): string
    {
        $normalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $label));

        if (str_ends_with($normalized, 's') && $normalized !== 's') {
            $normalized = substr($normalized, 0, -1);
        }

        if (in_array($normalized, ['pin', 'card'], true)) {
            return 'pin_card';
        }

        if (in_array($normalized, ['photo', 'image', 'templatedata', 'templateref', 'rawpayload', 'failurereason'], true)) {
            return 'pii';
        }

        return 'credential';
    }

    private static function separatedLabelIsCommKey(string $label): bool
    {
        $normalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $label));

        if (str_ends_with($normalized, 's') && $normalized !== 's') {
            $normalized = substr($normalized, 0, -1);
        }

        return in_array($normalized, ['pushcommkey', 'commkey', 'admscommkey'], true);
    }

    /**
     * Redact values following sensitive PHP serialize() keys.
     *
     * A complete `s:<length>:"...";` token is emitted and the original
     * terminator is consumed. String values whose *content* carries a
     * sensitive label are re-emitted with the corrected length. A malformed
     * declared length fails closed: the rest of the payload is truncated so a
     * key suffix cannot survive.
     *
     * Nested containers keep their safe siblings, but a sensitive nested
     * container is replaced by `N;`; the diagnostic shape is intentionally
     * lossy rather than risking a plaintext value. JSON re-encoding keeps
     * 20-digit integers as strings (see {@see self::encodeRedactedJson()}).
     */
    private static function redactSerializedValues(string $value): string
    {
        $pattern = '/(?<![A-Za-z0-9_])(?:a:(?:-?\d+|[A-Za-z]+):\{|O:(?:-?\d+|[A-Za-z]+):"(?:[^"\\\\]|\\\\.)*":(?:-?\d+|[A-Za-z]+):\{|s:(?:-?\d+|[A-Za-z]+):")/';
        $result = '';
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length && preg_match($pattern, $value, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $matches[0][1];
            $parsed = self::parseSerializedValue($value, $start, 0);

            if ($parsed === null) {
                return $result . substr($value, $offset, $start - $offset) . '[TRUNCATED]';
            }

            [$serialized, $end] = $parsed;
            $result .= substr($value, $offset, $start - $offset) . $serialized;
            $offset = $end;
        }

        return $result . substr($value, $offset);
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private static function parseSerializedValue(string $value, int $start, int $depth, bool $isKey = false): ?array
    {
        if ($depth > 64) {
            return null;
        }

        $length = strlen($value);

        if ($start >= $length) {
            return null;
        }

        if (! $isKey && substr($value, $start, 2) === 'N;') {
            return ['N;', $start + 2];
        }

        if (preg_match('/\Gb:[01];/', $value, $matches, 0, $start) === 1) {
            return [$matches[0], $start + strlen($matches[0])];
        }

        if (preg_match('/\Gi:-?\d+;/', $value, $matches, 0, $start) === 1) {
            return [$matches[0], $start + strlen($matches[0])];
        }

        if (! $isKey && preg_match('/\Gd:[^;]*;/', $value, $matches, 0, $start) === 1) {
            return [$matches[0], $start + strlen($matches[0])];
        }

        if (! $isKey && preg_match('/\G(?:R|r):\d+;/', $value, $matches, 0, $start) === 1) {
            return [$matches[0], $start + strlen($matches[0])];
        }

        if (preg_match('/\Gs:(\d+):"/', $value, $matches, 0, $start) === 1) {
            $dataStart = $start + strlen($matches[0]);
            $declaredLength = (int) $matches[1];

            if ($declaredLength < 0 || $dataStart + $declaredLength + 2 > $length) {
                return null;
            }

            if ($value[$dataStart + $declaredLength] !== '"'
                || $value[$dataStart + $declaredLength + 1] !== ';') {
                return null;
            }

            $end = $dataStart + $declaredLength + 2;
            $original = substr($value, $start, $end - $start);

            if ($isKey) {
                return [$original, $end];
            }

            $content = substr($value, $dataStart, $declaredLength);
            $redactedContent = self::redactText($content);

            if ($redactedContent === $content) {
                return [$original, $end];
            }

            return ['s:' . strlen($redactedContent) . ':"' . $redactedContent . '";', $end];
        }

        if (! $isKey && preg_match('/\Ga:(\d+):\{/', $value, $matches, 0, $start) === 1) {
            return self::parseSerializedContainer($value, $start, $matches, $depth);
        }

        if (! $isKey && preg_match('/\GO:(\d+):"/', $value, $matches, 0, $start) === 1) {
            $classStart = $start + strlen($matches[0]);
            $classLength = (int) $matches[1];

            if ($classStart + $classLength + 2 > $length
                || $value[$classStart + $classLength] !== '"'
                || $value[$classStart + $classLength + 1] !== ':') {
                return null;
            }

            $cursor = $classStart + $classLength + 2;

            if (preg_match('/\G(\d+):\{/', $value, $countMatches, 0, $cursor) !== 1) {
                return null;
            }

            return self::parseSerializedContainer($value, $start, [
                0 => substr($value, $start, $cursor + strlen($countMatches[0]) - $start),
                1 => $countMatches[1],
            ], $depth);
        }

        return null;
    }

    /**
     * @param  array{0: string, 1: string}  $header
     * @return array{0: string, 1: int}|null
     */
    private static function parseSerializedContainer(string $value, int $start, array $header, int $depth): ?array
    {
        $length = strlen($value);
        $count = (int) $header[1];
        $cursor = $start + strlen($header[0]);
        $output = $header[0];

        for ($i = 0; $i < $count; $i++) {
            $key = self::parseSerializedValue($value, $cursor, $depth + 1, true);

            if ($key === null) {
                return null;
            }

            [$keyToken, $cursor] = $key;
            $output .= $keyToken;

            $keyName = self::serializedKeyName($keyToken);
            $sensitive = $keyName !== null && self::isSensitiveName($keyName);

            $item = self::parseSerializedValue($value, $cursor, $depth + 1);

            if ($item === null) {
                return null;
            }

            [$itemToken, $cursor] = $item;
            $output .= $sensitive ? self::serializedPlaceholder($itemToken) : $itemToken;
        }

        if ($cursor >= $length || $value[$cursor] !== '}') {
            return null;
        }

        return [$output . '}', $cursor + 1];
    }

    private static function serializedKeyName(string $token): ?string
    {
        if (preg_match('/\As:(\d+):"/', $token, $matches) !== 1) {
            return null;
        }

        $dataStart = strlen($matches[0]);
        $declaredLength = (int) $matches[1];

        if (strlen($token) < $dataStart + $declaredLength + 2) {
            return null;
        }

        return substr($token, $dataStart, $declaredLength);
    }

    private static function serializedPlaceholder(string $token): string
    {
        return str_starts_with($token, 's:') ? 's:10:"[REDACTED]";' : 'N;';
    }

    private static function redactAuthorizationSchemes(string $value): string
    {
        $value = preg_replace(
            '/((?:authorization|proxy-authorization)\s*["\']?\s*[:=]\s*["\']?)(basic|digest)\s+[^\r\n;]+/i',
            '$1$2 [REDACTED]',
            $value
        );

        if ($value === null || preg_last_error() !== PREG_NO_ERROR) {
            return '[REDACTION FAILED]';
        }

        // Bare `Basic`/`Digest` credentials without the Authorization header
        // name. Require a base64-shaped token with at least one uppercase
        // letter, digit, `+`, `/`, or `=` so prose such as
        // `Basic authentication failed` survives.
        $value = preg_replace(
            '/(?<![A-Za-z0-9_])((?i:basic|digest)\s+)(?=[A-Za-z0-9+\/=]*[A-Z0-9+\/=])[A-Za-z0-9+\/=]{8,}/',
            '$1[REDACTED]',
            $value
        );

        if ($value === null || preg_last_error() !== PREG_NO_ERROR) {
            return '[REDACTION FAILED]';
        }

        $value = preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i', '$1[REDACTED]', $value);

        return $value ?? '[REDACTION FAILED]';
    }

    /**
     * Strip URL userinfo (`https://user:pass@host`) from free text and URLs.
     * An email address without a scheme is left alone.
     */
    private static function redactUrlUserInfo(string $value): string
    {
        $redacted = preg_replace('#(://)[^/\s:@]+(?::[^/\s@]*)?@#', '$1[REDACTED]@', $value);

        return $redacted ?? '[REDACTION FAILED]';
    }

    /**
     * Redact `key=value`, `key: value`, `key => 'value'`, and JSON pairs.
     *
     * The scan is offset-based instead of a single whole-line regex: when a
     * non-sensitive assignment is found, only its name and delimiter are
     * skipped so a later credential on the same line is still inspected.
     */
    private static function redactSensitiveAssignments(string $value): string
    {
        $result = '';
        $offset = 0;
        $lastEmit = 0;
        $length = strlen($value);

        while ($offset < $length && ($delimiterMatch = self::nextAssignmentDelimiter($value, $offset)) !== null) {
            [$delimiter, $delimiterStart] = $delimiterMatch;
            $valueStart = $delimiterStart + strlen($delimiter);

            // Fast path for one-character names (the `a=` pairs in a large
            // payload). No sensitive free-text name is that short, so the
            // expensive name normalisation can be skipped entirely.
            $preceding = $delimiterStart > $offset ? $value[$delimiterStart - 1] : '';
            $beforePreceding = $delimiterStart - 2 >= $offset ? $value[$delimiterStart - 2] : null;

            if (ctype_alnum($preceding)
                && ($beforePreceding === null || str_contains(" \t\r\n;,(){}|&=:", $beforePreceding))) {
                $offset = $valueStart;

                continue;
            }

            // Only the lexical token immediately before this delimiter can be
            // its name. A bounded token prevents a benign word earlier in a
            // long line from deciding this assignment, and evaluating its
            // suffix still catches `apushcommkey`, `x-pushcommkey`, and dotted
            // or quoted forms.
            $name = self::immediateAssignmentName($value, $delimiterStart, $offset);

            // Serialize type/length markers (`s:11`, `a:1:{`, `i:0;`,
            // `O:8:"..."`) are structure, never assignments. Rewriting them
            // would corrupt the payload the serialized pass just repaired.
            if (self::isSerializedTypeMarker($name, $delimiter, $value, $valueStart)) {
                $offset = $valueStart;

                continue;
            }

            if (strlen($name) < 3 || ! self::isSensitiveName($name)) {
                // Keep the name and delimiter, then keep scanning inside the
                // value so a later credential on the same line is examined.
                $offset = $valueStart;

                continue;
            }

            [$replacement, $valueEnd] = self::consumeAssignmentValue($value, $valueStart, $delimiter);

            if ($replacement !== null) {
                $result .= substr($value, $lastEmit, $valueStart - $lastEmit) . $replacement;
                $lastEmit = $valueEnd;
            }

            $offset = max($valueEnd, $valueStart);
        }

        if (preg_last_error() !== PREG_NO_ERROR) {
            return '[REDACTION FAILED]';
        }

        return $result . substr($value, $lastEmit);
    }

    /**
     * Find the next assignment delimiter without a per-byte regex call.
     *
     * @return array{0: string, 1: int}|null
     */
    private static function nextAssignmentDelimiter(string $value, int $offset): ?array
    {
        $length = strlen($value);
        $position = $offset;

        while ($position < $length) {
            $position += strcspn($value, '=:', $position);

            if ($position >= $length) {
                return null;
            }

            $character = $value[$position];
            $previous = $position > 0 ? $value[$position - 1] : '';

            if ($character === '=') {
                if ($previous === '=' || $previous === '!' || $previous === '<' || $previous === '>') {
                    $position++;

                    continue;
                }

                $next = $value[$position + 1] ?? '';

                if ($next === '=') {
                    $position += strspn($value, '=', $position);

                    continue;
                }

                if ($next === '>') {
                    return ['=>', $position];
                }

                return ['=', $position];
            }

            if (($value[$position + 1] ?? '') === ':' || $previous === '=' || $previous === '!' || $previous === '<' || $previous === '>') {
                $position++;

                continue;
            }

            return [':', $position];
        }

        return null;
    }

    /**
     * The lexical token immediately before an assignment delimiter.
     *
     * Spaces/tabs directly before the delimiter are skipped, then the token is
     * read backwards until whitespace, a statement/collection separator, or
     * 128 bytes. Quotes, brackets, and backslashes are trimmed so quoted,
     * array-style (`["pushcommkey"]`), and escaped (`\"pushcommkey\"`) names
     * reduce to their credential name.
     */
    private static function immediateAssignmentName(string $value, int $delimiterStart, int $floor): string
    {
        $min = max($floor, $delimiterStart - 128);
        $cursor = $delimiterStart - 1;

        while ($cursor >= $min && ($value[$cursor] === ' ' || $value[$cursor] === "\t")) {
            $cursor--;
        }

        $end = $cursor + 1;

        while ($cursor >= $min && ! str_contains(" \t\r\n;,(){}|&", $value[$cursor])) {
            $cursor--;
        }

        $token = substr($value, $cursor + 1, $end - $cursor - 1);

        return trim($token, " \t\n\r\0\x0B\"'`[]{}()$\\/");
    }

    private static function isSerializedTypeMarker(string $name, string $delimiter, string $value, int $valueStart): bool
    {
        if ($delimiter !== ':' || preg_match('/^(?:[saiObdNRr])$/', $name) !== 1) {
            return false;
        }

        return preg_match('/^(?:\d|;|\{|")/', substr($value, $valueStart, 4)) === 1;
    }

    /**
     * Consume one assignment value and return its replacement.
     *
     * A quoted value ends at the matching unescaped quote (never across a line
     * break); an unterminated quote is redacted to the end of the line. JSON
     * array/object values are replaced with a quoted scalar so the JSON stays
     * parseable.
     *
     * @return array{0: string|null, 1: int}
     */
    private static function consumeAssignmentValue(string $value, int $start, string $delimiter): array
    {
        $length = strlen($value);

        if ($start >= $length) {
            return [null, $start];
        }

        $cursor = $start + strspn($value, " \t", $start);
        $prefix = substr($value, $start, $cursor - $start);

        // YAML block scalars (`pushcommkey: |`, `>`, `|-`, `>+`) put the real
        // value on the following indented lines.
        if ($cursor < $length
            && ($value[$cursor] === '|' || $value[$cursor] === '>')
            && preg_match('/^[|>](?:[1-9][+-]?|[+-][1-9]?)?[ \t]*(?:#.*)?(?:\r\n|\r|\n|$)/', substr($value, $cursor, 64)) === 1) {
            [$yaml, $end] = self::consumeYamlBlockScalar($value, $cursor);

            return [$prefix . $yaml, $end];
        }

        // A support note may place the value on the next line:
        // `pushcommkey:\n123456`. Only consume it when the next non-empty line
        // looks like a value, so `token=\nkeep me` remains untouched.
        if ($cursor < $length && ($value[$cursor] === "\r" || $value[$cursor] === "\n")) {
            $lineStart = $cursor;

            while ($cursor < $length && str_contains("\r\n \t", $value[$cursor])) {
                $cursor++;
            }

            if ($cursor >= $length) {
                return [null, $lineStart];
            }

            $lineLength = strcspn($value, "\r\n", $cursor);
            $line = trim(substr($value, $cursor, $lineLength));

            if (! self::looksLikeAssignmentValue($line)) {
                return [null, $lineStart];
            }

            $prefix = substr($value, $lineStart, $cursor - $lineStart);
        }

        if ($cursor >= $length) {
            return [null, $cursor];
        }

        // `var_dump()`/`print_r()` annotate values with a type first:
        // `["pushcommkey"]=> string(6) "123456"` or `Array\n(`.
        if (preg_match('/^(?P<type>string|int|integer|float|double|bool|boolean|array|object)\b\s*(?:\(\s*\d+\s*\))?\s*/i', substr($value, $cursor, 256), $typeMatch) === 1) {
            $type = strtolower($typeMatch['type']);
            $cursor += strlen($typeMatch[0]);

            if ($cursor >= $length) {
                return [null, $cursor];
            }

            // print_r() wraps arrays in a balanced parenthesised block that is
            // not JSON. Redact the complete block, including nested values.
            if (($type === 'array' || $type === 'object') && $value[$cursor] === '(') {
                $end = self::findMatchingParen($value, $cursor);

                if ($end !== null) {
                    return [$prefix . '[REDACTED]', $end + 1];
                }
            }

            // A type annotation may itself be followed by a line break.
            $cursor += strspn($value, " \t\r\n", $cursor);

            if ($cursor >= $length) {
                return [null, $cursor];
            }
        }

        // An inline serialized value after a sensitive label
        // (`pushcommkey: s:6:"749201";`) must be consumed as one token,
        // otherwise the assignment scanner stops at the first quote and leaves
        // the key suffix behind.
        if (preg_match('/\G(?:s:(?:\d+|-\d+|[A-Za-z]+):"|a:(?:\d+|-\d+|[A-Za-z]+):\{|O:(?:\d+|-\d+|[A-Za-z]+):")/', substr($value, $cursor, 64)) === 1) {
            $parsedSerialized = self::parseSerializedValue($value, $cursor, 0);

            if ($parsedSerialized !== null) {
                return [$prefix . '[REDACTED]', $parsedSerialized[1]];
            }
        }

        $first = $value[$cursor];

        // A value may itself be an escaped quoted string (`\"749201\"`),
        // common in JSON-encoded exception messages. Treat the escaped quote
        // as the delimiter and emit an escaped placeholder.
        if ($first === '\\'
            && $cursor + 1 < $length
            && ($value[$cursor + 1] === '"' || $value[$cursor + 1] === "'")) {
            $quote = $value[$cursor + 1];
            $end = self::escapedQuotedValueEnd($value, $cursor, $quote);
            $placeholder = '\\' . $quote . '[REDACTED]' . '\\' . $quote;

            if ($end === null) {
                return [$prefix . $placeholder, $cursor + strcspn($value, "\r\n", $cursor)];
            }

            $inner = trim(substr($value, $cursor + 2, $end - $cursor - 2));

            if ($inner === '***' || $inner === '[REDACTED]') {
                return [null, $end + 2];
            }

            return [$prefix . $placeholder, $end + 2];
        }

        // The authorization-scheme pass already replaced the credential; keep
        // the scheme word and the placeholder instead of treating the
        // `[REDACTED]` bracket as a value delimiter.
        if (preg_match('/^(?:basic|digest|bearer)\s+\[REDACTED\]/i', substr($value, $cursor, 64)) === 1) {
            return [null, $cursor + strcspn($value, ";,&\r\n", $cursor)];
        }

        $jsonContext = false;

        if ($delimiter === ':') {
            $delimiterStart = $start - 1;
            $quotePosition = $delimiterStart - 1;

            while ($quotePosition >= 0 && ($value[$quotePosition] === ' ' || $value[$quotePosition] === "\t")) {
                $quotePosition--;
            }

            $jsonContext = $quotePosition >= 0 && $value[$quotePosition] === '"';
        }

        if ($first === '"' || $first === "'") {
            $end = self::quotedValueEnd($value, $cursor, $first);

            if ($end === null) {
                return [$prefix . '[REDACTED]', $cursor + strcspn($value, "\r\n", $cursor)];
            }

            return [$prefix . $first . '[REDACTED]' . $first, $end + 1];
        }

        if ($first === '[' || $first === '{') {
            $end = self::findBalancedJsonEnd($value, $cursor);

            if ($end !== null) {
                return [$prefix . ($jsonContext ? '"[REDACTED]"' : '[REDACTED]'), $end + 1];
            }

            return [$prefix . ($jsonContext ? '"[REDACTED]"' : '[REDACTED]'), $cursor + strcspn($value, "\r\n", $cursor)];
        }

        // A parenthesised wrapper (`("749201")`) hides the real token from the
        // unquoted span; consume the balanced block instead.
        if ($first === '(') {
            $end = self::findMatchingParen($value, $cursor);

            if ($end !== null) {
                return [$prefix . '[REDACTED]', $end + 1];
            }

            return [$prefix . '[REDACTED]', $cursor + strcspn($value, "\r\n", $cursor)];
        }

        $span = strcspn($value, "\r\n,;&)]}\"'", $cursor);

        // Keep digit-group separators such as `749,201` inside the value.
        while ($cursor + $span < $length && $value[$cursor + $span] === ',') {
            $digits = strspn($value, '0123456789', $cursor + $span + 1);

            if ($digits === 0) {
                break;
            }

            $span += 1 + $digits;
        }

        // A following `word=`/`word:` starts the next assignment. Stop before
        // it so `token=abc123 user=bob status=ok` keeps the later fields,
        // while a formatted value such as `123 456` is still consumed whole.
        $boundary = self::followingAssignmentOffset(substr($value, $cursor, $span));

        if ($boundary !== null) {
            $span = $boundary;
        }

        $candidate = rtrim(substr($value, $cursor, $span));
        $candidate = self::trimAssignmentValue($candidate);
        $span = strlen($candidate);

        if ($span === 0) {
            return [null, $cursor];
        }

        if (trim($candidate) === '' || str_contains($candidate, '[REDACTED]')) {
            return [null, $cursor + $span];
        }

        return [$prefix . ($jsonContext ? '"[REDACTED]"' : '[REDACTED]'), $cursor + $span];
    }

    private static function followingAssignmentOffset(string $candidate): ?int
    {
        if (preg_match('/\s+(?=[A-Za-z_][A-Za-z0-9_.-]*\s*[:=])/', $candidate, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $matches[0][1];
    }

    /**
     * Keep a plausible value run and drop trailing prose. A digit-group
     * credential (`123 456`) keeps every all-digit token; any other value
     * stops at the first non-digit token so `token=abc123 boom` does not
     * consume the diagnostic tail.
     */
    private static function trimAssignmentValue(string $candidate): string
    {
        if (preg_match('/[ \t]/', $candidate) !== 1) {
            return $candidate;
        }

        $parts = preg_split('/[ \t]+/', trim($candidate)) ?: [];

        if ($parts === []) {
            return $candidate;
        }

        $kept = [];

        foreach ($parts as $part) {
            if ($kept === [] || preg_match('/^\d+$/', $part) === 1) {
                $kept[] = $part;

                continue;
            }

            break;
        }

        return implode(' ', $kept);
    }

    private static function looksLikeAssignmentValue(string $line): bool
    {
        if ($line === '') {
            return false;
        }

        if (str_starts_with($line, '"')
            || str_starts_with($line, "'")
            || str_starts_with($line, '[')
            || str_starts_with($line, '{')) {
            return true;
        }

        if (preg_match('/^(?:string|int|integer|float|double|bool|boolean|array|object)\b\s*(?:\(\s*\d+\s*\))?/i', $line) === 1) {
            return true;
        }

        // A single bare token is a value. In a multi-word line, require a
        // digit-bearing token so a sentence such as `keep me` survives.
        if (preg_match('/^\S+$/', $line) === 1
            || preg_match('/^[A-Za-z0-9._~+=\/-]*\d[A-Za-z0-9._~+=\/-]*(?:\s|$)/', $line) === 1) {
            return true;
        }

        return false;
    }

    /**
     * The offset of the closing quote, or null when the quote is unterminated
     * before the next line break. Backslash escapes are respected.
     */
    private static function quotedValueEnd(string $text, int $start, string $quote): ?int
    {
        $length = strlen($text);

        for ($i = $start + 1; $i < $length; $i++) {
            $char = $text[$i];

            if ($char === '\\') {
                $i++;

                continue;
            }

            if ($char === $quote) {
                return $i;
            }

            if ($char === "\r" || $char === "\n") {
                return null;
            }
        }

        return null;
    }

    /**
     * The offset of the backslash that starts an escaped closing quote
     * (`\"...\"`), or null when it is unterminated before a line break.
     */
    private static function escapedQuotedValueEnd(string $text, int $start, string $quote): ?int
    {
        $length = strlen($text);

        for ($i = $start + 2; $i < $length - 1; $i++) {
            if ($text[$i] === "\r" || $text[$i] === "\n") {
                return null;
            }

            if ($text[$i] !== '\\' || $text[$i + 1] !== $quote) {
                continue;
            }

            $slashes = 0;

            for ($j = $i - 1; $j > $start && $text[$j] === '\\'; $j--) {
                $slashes++;
            }

            if ($slashes % 2 === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Consume a YAML block scalar (`|`, `>`, `|-`, `>+`) and its indented
     * body. The indicator and body are replaced by one placeholder; the final
     * newline is retained to keep the document's line structure.
     *
     * @return array{0: string, 1: int}
     */
    private static function consumeYamlBlockScalar(string $value, int $cursor): array
    {
        $length = strlen($value);
        $lineEnd = $cursor + strcspn($value, "\r\n", $cursor);
        $previousNewline = strrpos(substr($value, 0, $cursor), "\n");
        $lineStart = $previousNewline === false ? 0 : $previousNewline + 1;
        $parentIndent = strspn($value, " \t", $lineStart);

        $i = $lineEnd;

        if ($i < $length && $value[$i] === "\r") {
            $i++;
        }

        if ($i < $length && $value[$i] === "\n") {
            $i++;
        }
        $blockEnd = $i;
        $consumedBody = false;

        while ($i < $length) {
            $currentEnd = $i + strcspn($value, "\r\n", $i);
            $line = substr($value, $i, $currentEnd - $i);

            if (trim($line) !== '' && strspn($line, " \t") <= $parentIndent) {
                break;
            }

            if (trim($line) !== '') {
                $consumedBody = true;
            }

            $i = $currentEnd;

            if ($i < $length && $value[$i] === "\r") {
                $i++;
            }

            if ($i < $length && $value[$i] === "\n") {
                $i++;
            }

            $blockEnd = $i;
        }

        if (! $consumedBody) {
            // No indented body: a zero-indent scalar is malformed YAML but
            // still carries the value. Fail closed on the next non-empty
            // line(s) until a sibling key, a blank line, or end of input.
            $cursor = $i;

            while ($cursor < $length) {
                $currentEnd = $cursor + strcspn($value, "\r\n", $cursor);
                $line = substr($value, $cursor, $currentEnd - $cursor);

                if (trim($line) === ''
                    || preg_match('/^[ \t]*[A-Za-z_][A-Za-z0-9_.-]*[ \t]*:/', $line) === 1) {
                    return ['[REDACTED]', $cursor];
                }

                $cursor = $currentEnd;

                if ($cursor < $length && $value[$cursor] === "\r") {
                    $cursor++;
                }

                if ($cursor < $length && $value[$cursor] === "\n") {
                    $cursor++;
                }

                return ['[REDACTED]' . ($value[$cursor - 1] === "\n" ? "\n" : ''), $cursor];
            }

            return ['[REDACTED]', $lineEnd];
        }

        return ['[REDACTED]' . ($value[$blockEnd - 1] === "\n" ? "\n" : ''), $blockEnd];
    }

    /**
     * Find the matching bracket/brace for a JSON-like value, respecting quoted
     * strings so a `]` inside a value does not end the container early.
     */
    private static function findBalancedJsonEnd(string $text, int $start): ?int
    {
        $length = strlen($text);
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($inString) {
                if ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{' || $char === '[') {
                $depth++;

                continue;
            }

            if ($char === '}' || $char === ']') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Find the end of a bare escaped JSON object (`{\"key\":\"value\"}`).
     * In this form `\"` is the quote delimiter, not an escaped quote inside a
     * string, so the ordinary JSON scanner cannot be used.
     */
    private static function findBalancedEscapedJsonEnd(string $text, int $start): ?int
    {
        $length = strlen($text);
        $depth = 0;
        $inString = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($char === '\\' && $i + 1 < $length) {
                $next = $text[$i + 1];

                if ($next === '"') {
                    $inString = ! $inString;
                    $i++;

                    continue;
                }

                if ($next === '\\') {
                    $i++;

                    continue;
                }
            }

            if ($inString) {
                continue;
            }

            if ($char === '{' || $char === '[') {
                $depth++;

                continue;
            }

            if ($char === '}' || $char === ']') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Redact percent-encoded pairs such as `pushcommkey%3D123456` and
     * `pushcommkey%25253D123456`.
     */
    private static function redactEncodedAssignments(string $value): string
    {
        $redacted = preg_replace_callback(
            '/(?<![A-Za-z0-9_])([A-Za-z0-9_.\-%\[\]\'"]+?)((?:%3D|%25(?:25){0,2}3D))([^\s&,;]+)/i',
            static function (array $matches): string {
                if (! self::isSensitiveName($matches[1])) {
                    return $matches[0];
                }

                return $matches[1] . $matches[2] . '[REDACTED]';
            },
            $value
        );

        return $redacted ?? '[REDACTION FAILED]';
    }

    /**
     * Redact URL-style parameters embedded in a free-text diagnostic without
     * touching line breaks. This closes name-embedded values such as
     * `?pushcommkey%20749201` and nested values such as
     * `?note=pushcommkey+749201` that are not plain `name=value` assignments.
     */
    private static function redactUrlParametersInText(string $value): string
    {
        $redacted = preg_replace_callback(
            '/([?&])([^=&#\s]+)(=([^&#\s]*))?/',
            static function (array $matches): string {
                $rawName = $matches[2];
                $decodedName = self::decodeUrlLayers($rawName);
                $separator = strpos($decodedName, '=');
                $nameForMatch = $separator === false ? $decodedName : substr($decodedName, 0, $separator);

                if (self::isSensitiveName($nameForMatch)) {
                    if ($separator !== false || preg_match('/[\s,;\/+]/', $decodedName) === 1) {
                        $canonical = $separator !== false
                            ? self::canonicalUrlName($nameForMatch)
                            : self::canonicalUrlName(preg_split('/[\s,;\/+]/', $decodedName)[0] ?? '');

                        return $canonical !== null
                            ? $matches[1] . $canonical . '=[REDACTED]'
                            : $matches[1] . '[REDACTED]';
                    }

                    $canonical = self::canonicalUrlNameForRaw($rawName, $decodedName);

                    if ($canonical !== null) {
                        return $matches[1] . $canonical . '=[REDACTED]';
                    }

                    if (! self::isArrayStyleUrlName($decodedName) && preg_match('/\d/', $decodedName) === 1) {
                        return $matches[1] . '[REDACTED]';
                    }

                    return $matches[1] . '[REDACTED]';
                }

                if (isset($matches[4]) && $matches[4] !== '') {
                    $decodedValue = self::decodeUrlLayers($matches[4]);

                    if (self::valueEmbedsSensitiveName($decodedValue)
                        || (self::containsSensitiveName($decodedValue)
                            && (str_contains($decodedValue, '=')
                                || self::redactSeparatedSensitiveLabels($decodedValue) !== $decodedValue))) {
                        return $matches[1] . $rawName . '=[REDACTED]';
                    }
                }

                return $matches[0];
            },
            $value
        );

        return $redacted ?? '[REDACTION FAILED]';
    }

    /**
     * Redact comm-key call arguments and bearer arguments in historical traces
     * (`verifyCommKey('749201')`, `AuthenticateAdmsDevice->handle('749201')`).
     */
    private static function redactSensitiveCallArguments(string $value): string
    {
        $pattern = '/(?<![A-Za-z0-9_])(verifyCommKeyWithoutLegacyFallback|verifyCommKey|hashCommKey|sanitizeCommKey)\s*\(\s*("(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^,)]*)/';

        $value = preg_replace_callback($pattern, static function (array $matches): string {
            $argument = ltrim($matches[2]);

            if ($argument === '') {
                return $matches[1] . '(';
            }

            if ($argument[0] === '"' || $argument[0] === "'") {
                return $matches[1] . '(' . $argument[0] . '[REDACTED]' . $argument[0];
            }

            return $matches[1] . '([REDACTED]';
        }, $value);

        if ($value === null) {
            return '[REDACTION FAILED]';
        }

        $handlePattern = '/(?<![A-Za-z0-9_])((?:AuthenticateAdmsDevice|BiometricWebhookController)->handle)\s*\(/';
        $result = '';
        $offset = 0;

        while (preg_match($handlePattern, $value, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $matches[0][1];
            $open = $start + strlen($matches[0][0]) - 1;
            $end = self::findMatchingParen($value, $open);

            if ($end === null) {
                // A truncated trace can end inside the call. Do not append the
                // tail unredacted just because the closing parenthesis is gone.
                $result .= substr($value, $offset, $start - $offset);
                $result .= $matches[1][0] . '([REDACTED]';
                $offset = strlen($value);

                break;
            }

            $result .= substr($value, $offset, $start - $offset);
            $result .= $matches[1][0] . '([REDACTED])';
            $offset = $end + 1;
        }

        return $result . substr($value, $offset);
    }

    private static function findMatchingParen(string $text, int $open): ?int
    {
        $length = strlen($text);
        $depth = 0;
        $inString = false;
        $quote = '';
        $escaped = false;

        for ($i = $open; $i < $length; $i++) {
            $char = $text[$i];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($inString) {
                if ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $quote = $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Redact bearer tokens that travel in the URL path (the biometric webhook
     * device token).
     */
    private static function redactRouteTokens(string $value): string
    {
        $separator = '(?:%2F|%25(?:25)?2F|/)';

        $patterns = [
            // /api/v1/biometric/webhook/<token>, including repeated slashes and
            // mixed/double percent-encoded separators.
            '#(' . $separator . '+(?:v1' . $separator . '+)?biometric' . $separator . '+webhook' . $separator . '+)[A-Za-z0-9._~-]+#i' => '$1[REDACTED]',
            // Generic /webhook/<token>. Require 16+ characters so short
            // non-credential paths documented in the ADR survive.
            '#(' . $separator . '+webhook' . $separator . '+)[A-Za-z0-9._~-]{16,}#i' => '$1[REDACTED]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value);

            if ($value === null) {
                return '[REDACTION FAILED]';
            }
        }

        return $value;
    }

    /**
     * Redact standalone 48/64-hex bearer tokens (webhook secrets and device
     * tokens) in historical messages and traces.
     */
    private static function redactBareTokens(string $value): string
    {
        return preg_replace('/(?<![A-Fa-f0-9])[A-Fa-f0-9]{48,64}(?![A-Fa-f0-9])/', '[REDACTED]', $value) ?? '[REDACTION FAILED]';
    }

    /**
     * Whether a decoded assignment name is in the free-text credential/PII
     * name set. Array-key masking still uses the broader SENSITIVE_KEYS list.
     *
     * Dotted paths and whitespace-separated labels are normalised, `X-` header
     * prefixes and array suffixes are stripped, and percent-encoded names are
     * decoded up to three times.
     */
    private static function isSensitiveName(string $name): bool
    {
        $decoded = $name;

        for ($i = 0; $i < 3; $i++) {
            $next = urldecode($decoded);

            if ($next === $decoded) {
                break;
            }

            $decoded = $next;
        }

        $decoded = trim($decoded, " \t\n\r\0\x0B\"'`[]{}()$\\/");
        $decoded = (string) preg_replace('/^\$_(?:GET|POST|REQUEST|COOKIE|SERVER)\s*/i', '', $decoded);
        $decoded = trim($decoded, " \t\n\r\0\x0B\"'`[]{}()$\\/");

        $segments = preg_split('/[._\-\s]+/', $decoded, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($segments as $segment) {
            if (self::normalizedNameIsSensitive($segment)) {
                return true;
            }
        }

        return self::normalizedNameIsSensitive($decoded);
    }

    private static function normalizedNameIsSensitive(string $segment): bool
    {
        $normalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $segment));

        if ($normalized === '') {
            return false;
        }

        $names = self::normalizedFreeTextNames();

        if (in_array($normalized, $names, true)) {
            return true;
        }

        // Plural PII names: cards, pins, photos, images, tokens.
        if (str_ends_with($normalized, 's') && in_array(substr($normalized, 0, -1), $names, true)) {
            return true;
        }

        // Header forms: X-PushCommKey, x_api_key, X-Device-Token.
        if (str_starts_with($normalized, 'x') && in_array(substr($normalized, 1), $names, true)) {
            return true;
        }

        foreach ($names as $name) {
            if ($name === '') {
                continue;
            }

            $nameLength = strlen($name);

            // Full credential names may be concatenated on either side:
            // `apushcommkey`, `pushcommkey_old`, `webhook_secret_rotation`.
            if ($nameLength >= 7) {
                if (str_ends_with($normalized, $name) || str_starts_with($normalized, $name)) {
                    return true;
                }

                continue;
            }

            // Short names need a semantic token boundary. This keeps
            // `discard`, `spin`, `postcard`, `wildcard`, `hairpin`, and
            // `scorecard` intact while still matching `user_pin` and
            // `pin_hash`.
            if (str_ends_with($normalized, $name)) {
                $prefix = substr($normalized, 0, -$nameLength);

                if (self::isCredentialPrefix($prefix)) {
                    return true;
                }
            }

            if (str_starts_with($normalized, $name)) {
                $suffix = substr($normalized, $nameLength);

                if (self::isCredentialSuffix($suffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isCredentialPrefix(string $prefix): bool
    {
        return in_array($prefix, [
            'user',
            'member',
            'device',
            'gym',
            'adms',
            'push',
            'comm',
            'access',
            'auth',
            'api',
            'app',
            'client',
            'proxy',
            'webhook',
            'employee',
            'biometric',
            'customer',
            'profile',
        ], true);
    }

    private static function isCredentialSuffix(string $suffix): bool
    {
        return in_array($suffix, [
            'id',
            'ids',
            'hash',
            'salt',
            'value',
            'values',
            'data',
            'number',
            'no',
            'num',
            'type',
            'token',
            'code',
            'key',
            'keys',
            'secret',
        ], true);
    }

    /**
     * @return array<int, string>
     */
    private static function normalizedFreeTextNames(): array
    {
        /** @var array<int, string>|null $names */
        static $names = null;

        if ($names === null) {
            $names = array_values(array_unique(array_map(
                static fn (string $name): string => strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $name)),
                self::SENSITIVE_NAMES
            )));
        }

        return $names;
    }

    private static function containsSensitiveName(string $text): bool
    {
        $normalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $text));

        if ($normalized === '') {
            return false;
        }

        foreach (self::normalizedFreeTextNames() as $name) {
            if ($name !== '' && str_contains($normalized, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A structural JSON document may carry a sensitive key from the broader
     * SENSITIVE_KEYS set (`data`, `fingerprint`, `user_id`, ...) that is not a
     * free-text credential name. The prefilter must still run the JSON path.
     */
    private static function containsStructuralJsonKey(string $text): bool
    {
        return preg_match('/[\[{]\s*"/', $text) === 1;
    }

    /**
     * Whether a URL parameter value is a sensitive name with key material
     * attached (`pushcommkeyABC`). A whitespace-separated benign phrase such
     * as `token expired` is not treated as a credential.
     */
    private static function valueEmbedsSensitiveName(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '' || preg_match('/\s/', $trimmed) === 1) {
            return false;
        }

        $normalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $trimmed));

        foreach (self::normalizedFreeTextNames() as $name) {
            if ($name !== '' && str_starts_with($normalized, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redact sensitive query values from a URL, including percent-encoded and
     * double/triple-encoded names, and strip CR/LF/control characters so a URL
     * cannot inject a new Markdown section.
     */
    public static function redactUrl(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }

        $value = preg_replace('/[\x00-\x1F\x7F]+/', '', $url) ?? $url;
        $value = self::redactRouteTokens($value);
        $value = self::redactUrlUserInfo($value);
        $value = self::capUrlParameters($value, 200);

        // Decode only the parameter name for matching. The value stays exactly
        // as it was, so an encoded `&`, `#`, or newline inside a secret cannot
        // split it into a second parameter or inject a Markdown section.
        $redacted = preg_replace_callback(
            '/([?&])([^=&#\s]+)(=([^&#\s]*))?/',
            static function (array $matches): string {
                $rawName = $matches[2];
                $decodedName = self::decodeUrlLayers($rawName);
                $separator = strpos($decodedName, '=');
                $nameForMatch = $separator === false ? $decodedName : substr($decodedName, 0, $separator);

                if (self::isSensitiveName($nameForMatch)) {
                    if ($separator !== false) {
                        $canonical = self::canonicalUrlName($nameForMatch);

                        return $canonical !== null
                            ? $matches[1] . $canonical . '=[REDACTED]'
                            : $matches[1] . '[REDACTED]';
                    }

                    // A name with no `=` can still embed a value after a
                    // whitespace/plus/comma/semicolon/slash separator. Never
                    // echo that raw name: use its canonical sensitive part or
                    // drop the whole parameter.
                    if (preg_match('/[\s,;\/+]/', $decodedName) === 1) {
                        $canonical = self::canonicalUrlName(preg_split('/[\s,;\/+]/', $decodedName)[0] ?? '');

                        return $canonical !== null
                            ? $matches[1] . $canonical . '=[REDACTED]'
                            : $matches[1] . '[REDACTED]';
                    }

                    $canonical = self::canonicalUrlNameForRaw($rawName, $decodedName);

                    if ($canonical !== null) {
                        return $matches[1] . $canonical . '=[REDACTED]';
                    }

                    if (! self::isArrayStyleUrlName($decodedName) && preg_match('/\d/', $decodedName) === 1) {
                        return $matches[1] . '[REDACTED]';
                    }

                    return $matches[1] . '[REDACTED]';
                }

                // A nested URL in a parameter value can carry its own
                // credentials (`?next=https%3A%2F%2F...%3Fpushcommkey%3D...`).
                // Decode multiple layers because proxies may re-encode the
                // nested URL more than once.
                if (isset($matches[4]) && $matches[4] !== '') {
                    $decodedValue = self::decodeUrlLayers($matches[4]);

                    if (self::valueEmbedsSensitiveName($decodedValue)
                        || (self::containsSensitiveName($decodedValue)
                            && (str_contains($decodedValue, '=')
                                || preg_match('/(?:%3D|%25(?:25){0,2}3D)/i', $matches[4]) === 1
                                || self::redactSeparatedSensitiveLabels($decodedValue) !== $decodedValue))) {
                        return $matches[1] . $rawName . '=[REDACTED]';
                    }
                }

                return $matches[0];
            },
            $value
        );

        return $redacted ?? '[REDACTION FAILED]';
    }

    private static function decodeUrlLayers(string $value): string
    {
        for ($i = 0; $i < 3; $i++) {
            $next = urldecode($value);

            if ($next === $value) {
                break;
            }

            $value = $next;
        }

        return $value;
    }

    private static function canonicalUrlName(string $candidate): ?string
    {
        $candidate = trim($candidate);

        if ($candidate === ''
            || preg_match('/[\s,;\/+=&]/', $candidate) === 1
            || preg_match('/^[A-Za-z0-9_.\-\[\]%]+$/', $candidate) !== 1
            || ! self::isSensitiveName($candidate)) {
            return null;
        }

        $decoded = trim(self::decodeUrlLayers($candidate), " \t\n\r\0\x0B\"'`[]{}()$\\/");
        $normalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $decoded));

        if ($normalized === '') {
            return null;
        }

        // Preserve a safe name that is exactly a known credential name, an
        // `x-` header form, or an array-style name; none of these embed key
        // material. A name with extra characters (`pushcommkeyABC`) must not
        // be echoed.
        foreach (self::SENSITIVE_NAMES as $name) {
            $nameNormalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $name));

            if ($nameNormalized !== ''
                && ($normalized === $nameNormalized
                    || $normalized === 'x' . $nameNormalized
                    || ($normalized === $nameNormalized . '0' && self::isArrayStyleUrlName($decoded)))) {
                return $candidate;
            }
        }

        // Split at the longest known sensitive-name prefix/suffix and emit
        // only that canonical name.
        $best = null;

        foreach (self::SENSITIVE_NAMES as $name) {
            $nameNormalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $name));

            if ($nameNormalized === '') {
                continue;
            }

            if (str_starts_with($normalized, $nameNormalized)
                || str_ends_with($normalized, $nameNormalized)) {
                if ($best === null || strlen($nameNormalized) > strlen((string) preg_replace('/[^A-Za-z0-9]+/', '', $best))) {
                    $best = $name;
                }
            }
        }

        return $best;
    }

    /**
     * Preserve the raw spelling of an exact safe name (including percent
     * encoding and an array suffix) while still canonicalising a name that
     * embeds key material (`pushcommkeyABC` -> `pushcommkey`).
     */
    private static function canonicalUrlNameForRaw(string $rawName, string $decodedName): ?string
    {
        if (self::isSafeExactSensitiveName($decodedName)) {
            return $rawName;
        }

        return self::canonicalUrlName($decodedName);
    }

    private static function isSafeExactSensitiveName(string $decoded): bool
    {
        if (self::isArrayStyleUrlName($decoded) && self::isSensitiveName($decoded)) {
            // Preserve only when the base before the array suffix is itself an
            // exact known name. `pushcommkey[0]` is safe; `pushcommkeyABC[0]`
            // still embeds key material and must be canonicalised.
            $base = (string) preg_replace('/\[.*$/', '', $decoded);
            $baseNormalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $base));

            foreach (self::SENSITIVE_NAMES as $name) {
                $nameNormalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $name));

                if ($nameNormalized !== ''
                    && ($baseNormalized === $nameNormalized || $baseNormalized === 'x' . $nameNormalized)) {
                    return true;
                }
            }

            return false;
        }

        $normalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $decoded));

        foreach (self::SENSITIVE_NAMES as $name) {
            $nameNormalized = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $name));

            if ($nameNormalized !== ''
                && ($normalized === $nameNormalized || $normalized === 'x' . $nameNormalized)) {
                return true;
            }
        }

        return false;
    }

    private static function isArrayStyleUrlName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_.\-]+(?:\[\d*\])+$/', $name) === 1;
    }

    /**
     * Keep URL redaction bounded. Everything past the cap is removed rather
     * than processed or left raw.
     */
    private static function capUrlParameters(string $value, int $cap): string
    {
        $queryStart = strpos($value, '?');

        if ($queryStart === false) {
            return $value;
        }

        $fragmentStart = strpos($value, '#', $queryStart);
        $queryEnd = $fragmentStart === false ? strlen($value) : $fragmentStart;
        $position = $queryStart;
        $parameters = 1;

        while (($position = strpos($value, '&', $position + 1)) !== false && $position < $queryEnd) {
            $parameters++;

            if ($parameters > $cap) {
                return substr($value, 0, $position) . '&[TRUNCATED]';
            }
        }

        return $value;
    }

    /**
     * Recursively redacts sensitive biometric and credential keys from an array.
     *
     * String values that contain a JSON object/array are decoded and redacted
     * recursively. This is a backstop for payloads that embed serialized state
     * (for example a Livewire snapshot) rather than passing a nested array.
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
                if (in_array($normalizedKey, ['error', 'errors', 'exception'], true)) {
                    // Generic diagnostic keys keep their benign prefix; only
                    // credential/PII leaves inside them are replaced.
                    if (is_array($value)) {
                        $redacted[$key] = self::redactStrings(self::redact($value));
                    } elseif (is_string($value)) {
                        $redacted[$key] = self::redactText($value);
                    } else {
                        $redacted[$key] = $value;
                    }
                } elseif (is_array($value) && in_array($normalizedKey, self::CONTAINER_KEYS, true)) {
                    // `data` is a container in Livewire/request payloads; recurse so
                    // benign siblings survive instead of collapsing the object.
                    $redacted[$key] = self::redact($value);
                } elseif (is_string($value) && in_array($normalizedKey, self::CONTAINER_KEYS, true) && self::looksLikeJson($value)) {
                    $redacted[$key] = self::redactJsonString($value);
                } else {
                    // Credentials are never correlation-masked: a two-character
                    // prefix and suffix of a 6-digit PIN still leaks a third of
                    // the secret.
                    $redacted[$key] = '***';
                }
            } elseif (is_array($value)) {
                $redacted[$key] = self::redact($value);
            } elseif (is_string($value) && self::looksLikeJson($value)) {
                $redacted[$key] = self::redactJsonString($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Strip Livewire's serialized component state before storing a request
     * payload.
     *
     * Filament forms are Livewire components. The request carries the rendered
     * form state in `components[0].snapshot` and in the dotted `updates` map;
     * both contain raw input values (including a newly typed comm key or
     * webhook secret). The component name is captured separately, so neither
     * structure is needed for diagnosis.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function sanitizeRequestData(array $data): array
    {
        unset($data['components'], $data['updates']);

        $budget = self::MAX_REQUEST_BYTES;
        $leaves = self::MAX_REQUEST_LEAVES;
        $data = self::capStringLeaves($data, $budget, $leaves);

        return self::redactStrings(self::redact($data));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function capStringLeaves(array $data, int &$budget, int &$leaves): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::capStringLeaves($value, $budget, $leaves);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            if ($budget <= 0 || $leaves <= 0) {
                $data[$key] = '[TRUNCATED]';

                continue;
            }

            $leaves--;

            if (strlen($value) > $budget) {
                $data[$key] = mb_strcut($value, 0, $budget, 'UTF-8') . '[TRUNCATED]';
                $budget = 0;

                continue;
            }

            $budget -= strlen($value);
        }

        return $data;
    }

    private static function looksLikeJson(string $value): bool
    {
        if ($value === '' || strlen($value) > self::MAX_TEXT_BYTES) {
            return false;
        }

        $first = ltrim($value)[0] ?? '';

        return $first === '{' || $first === '[';
    }

    private static function redactJsonString(string $value): string
    {
        return self::redactJsonText($value) ?? self::redactText($value);
    }

    /**
     * Mask a single string value (e.g. employee number, card number).
     *
     * Correlation-grade only: the first and last two characters are deliberately retained, and
     * for a device-native row the masked `template_ref` is the member id. See the class
     * docblock for the boundary.
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
        // Free-text/PII names are shared with URL redaction so the two cannot
        // drift (x-comm-key, adms_comm_key, pin, template_data, ...).
        if (self::normalizedNameIsSensitive($keyStr)) {
            return true;
        }

        // 1. Exact match on normalized key
        if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        // 2. Dotted payload paths, for example `data.adms_comm_key` from a
        // Livewire update map. Match each segment with the same rules.
        if (str_contains($keyStr, '.')) {
            foreach (explode('.', $keyStr) as $segment) {
                $normalizedSegment = strtolower(str_replace(['_', '-'], '', $segment));

                if ($normalizedSegment !== '' && self::isSensitiveKey($segment, $normalizedSegment)) {
                    return true;
                }
            }
        }

        // 3. Token boundary match (splits snake_case, kebab-case, and camelCase)
        $tokens = preg_split('/(?=[A-Z])|[_\-\s]+/', $keyStr, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lowerTokens = array_map('strtolower', $tokens);

        foreach ($lowerTokens as $token) {
            if (in_array($token, self::SENSITIVE_KEYS, true)) {
                return true;
            }
        }

        // 4. Whole-word boundary match to prevent over-redaction like 'mapping' matching 'pin'
        $spacedKey = str_replace(['_', '-', '.'], ' ', $keyStr);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (preg_match('/\b' . preg_quote($sensitive, '/') . '\b/i', $spacedKey)) {
                return true;
            }
        }

        return false;
    }
}
