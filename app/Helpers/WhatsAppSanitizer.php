<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Str;

class WhatsAppSanitizer
{
    public const PHONE_MASKED = '[PHONE_MASKED]';

    private const META_TOKEN_PATTERN = '/EAA[a-zA-Z0-9_\-]+/';

    private const BEARER_TOKEN_PATTERN = '/Bearer\s+[a-zA-Z0-9\-._~+\/]+=*/i';

    private const TWILIO_SID_PATTERN = '/\b(AC|SK|SM|MM|MG|CA|US)[a-f0-9]{32}\b/i';

    private const PHONE_PATTERN = '/(?:\+(?:[\s().\-]*\d){7,15}\b)|(?:\b\d{1,3}[\s.\-])?\(?\d{3}\)?[\s.\-]\d{3,4}[\s.\-]\d{4}\b|(?:\b\d{1,3}[\s.\-])?\(\d{3}\)[\s.\-]?\d{3,4}[\s.\-]?\d{4}\b|\b\d{7,15}\b/';

    private const LONG_NUMERIC_PII_PATTERN = '/\b\d{16,31}\b/';

    private const HIGH_ENTROPY_HEX_PATTERN = '/\b(?!(?:AC|SK|SM|MM|MG|CA|US)[a-fA-F0-9]{32}\b)[a-fA-F0-9]{32,64}\b/i';

    private const HIGH_ENTROPY_ALPHANUMERIC_PATTERN = '/\b(?!(?:AC|SK|SM|MM|MG|CA|US)[a-fA-F0-9]{32}\b)[a-zA-Z0-9_\-]{32,128}\b/i';

    private const MEDIA_URL_PATTERN = '/https?:\/\/[^\s<>"\'\)]+\.(?:jpg|jpeg|png|gif|webp|pdf|mp4|mp3|ogg|aac|docx|xlsx|csv)(?:\?[^\s<>"\'\)]*)?/i';

    private const SIGNED_URL_PATTERN = '/https?:\/\/[^\s<>"\'\)]+[?&](?:sig|signature|token|auth|key|expires|AWSAccessKeyId|X-Amz-[a-zA-Z0-9_\-]+)=[^\s<>"\'\)]*/i';

    private const MAX_FAILURE_REASON_LENGTH = 500;

    private const TRUNCATION_SUFFIX = '...';

    /**
     * Dedicated method for masking phone numbers in logs and outputs.
     * Leaves the last 4 characters visible for diagnostic tracing.
     */
    public static function maskPhoneNumber(string $phone): string
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            return '';
        }

        $length = strlen($trimmed);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return Str::mask($trimmed, '*', 0, -4);
    }

    /**
     * Sanitize and truncate error messages before storing in DB or logging.
     *
     * @param  array<int, string>  $customStringsToMask
     */
    public static function sanitizeError(string $error, array $customStringsToMask = []): string
    {
        $patterns = [
            self::SIGNED_URL_PATTERN => '[URL_MASKED]',
            self::MEDIA_URL_PATTERN => '[URL_MASKED]',
            self::META_TOKEN_PATTERN => '[TOKEN_MASKED]',
            self::BEARER_TOKEN_PATTERN => 'Bearer [TOKEN_MASKED]',
            self::TWILIO_SID_PATTERN => '[SID_MASKED]',
            self::LONG_NUMERIC_PII_PATTERN => '[PII_MASKED]',
            self::PHONE_PATTERN => '[PHONE_MASKED]',
            self::HIGH_ENTROPY_HEX_PATTERN => '[TOKEN_MASKED]',
            self::HIGH_ENTROPY_ALPHANUMERIC_PATTERN => '[TOKEN_MASKED]',
        ];

        $sanitized = $error;

        // Mask custom strings (such as member names, template variables, and media URLs) to prevent PII leaks
        foreach ($customStringsToMask as $string) {
            $trimmed = trim($string);
            if ($trimmed !== '') {
                $escaped = preg_quote($trimmed, '/');
                $result = preg_replace('/' . $escaped . '/i', '[PII_MASKED]', $sanitized);
                if ($result !== null) {
                    $sanitized = $result;
                }
            }
        }
        foreach ($patterns as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $sanitized);
            if ($result !== null) {
                $sanitized = $result;
            } else {
                $sanitized = '[UNSANITIZABLE_ERROR]';
                break;
            }
        }

        if ($sanitized !== '[UNSANITIZABLE_ERROR]') {
            $stripped = preg_replace('/[\x00-\x1F\x7F]/', '', $sanitized);
            if ($stripped === null) {
                $sanitized = '[UNSANITIZABLE_ERROR]';
            } else {
                $sanitizedClean = preg_replace('/[^\x00-\x7F]+/', '[NON_ASCII]', $stripped);
                if ($sanitizedClean === null || $sanitizedClean === '') {
                    $sanitized = '[UNSANITIZABLE_ERROR]';
                } else {
                    $sanitized = $sanitizedClean;
                }
            }
        }

        return Str::limit($sanitized, self::MAX_FAILURE_REASON_LENGTH - strlen(self::TRUNCATION_SUFFIX), self::TRUNCATION_SUFFIX);
    }
}
