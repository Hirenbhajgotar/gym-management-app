<?php

declare(strict_types=1);

namespace App\Helpers;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Throwable;
use Twilio\Exceptions\RestException;

class WhatsAppFailureClassifier
{
    private const CONNECTION_ERRNOS = [
        6,  // CURLE_COULDNT_RESOLVE_HOST
        7,  // CURLE_COULDNT_CONNECT
        28, // CURLE_OPERATION_TIMEOUTED
        35, // CURLE_SSL_CONNECT_ERROR
        55, // CURLE_SEND_ERROR
        56, // CURLE_RECV_ERROR
    ];

    /**
     * Determine if a Throwable represents a transient connection error.
     */
    public static function isConnectionException(Throwable $e): bool
    {
        if ($e instanceof ConnectionException || $e instanceof ConnectException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $handlerContext = $e->getHandlerContext();
            if (isset($handlerContext['errno']) && in_array($handlerContext['errno'], self::CONNECTION_ERRNOS, true)) {
                return true;
            }
        }

        $previous = $e->getPrevious();
        if ($previous instanceof Throwable && self::isConnectionException($previous)) {
            return true;
        }

        if ($e instanceof RestException) {
            $statusCode = $e->getStatusCode();
            // Note: 429 is a rate limit, handled separately.
            if (self::isTransientServerError($statusCode)) {
                return true;
            }
        }

        return self::isConnectionMessage($e->getMessage());
    }

    /**
     * Determine if an HTTP status code represents a transient server/timeout error (e.g. 408, 5xx).
     */
    public static function isTransientServerError(int $statusCode): bool
    {
        return in_array($statusCode, [408, 500, 502, 503, 504, 524], true);
    }

    /**
     * Determine if an HTTP status code is a 429 rate limit.
     */
    public static function isRateLimitStatusCode(int $statusCode): bool
    {
        return $statusCode === 429;
    }

    /**
     * Determine if a message indicates a connection/timeout issue.
     */
    public static function isConnectionMessage(string $message): bool
    {
        return (bool) preg_match('/\b(connection\s*(refused|error|failed|reset)|(?:connect|connection|operation|request|network|twilio)?\s*timed?\s*out|(?:connect|connection|operation|request|network|twilio)?\s*timeout|curl\s*error\s*\d+|unable\s*to\s*connect|net::err)\b/i', $message);
    }

    /**
     * Determine if a Throwable represents a rate limit error.
     */
    public static function isRateLimitException(Throwable $e): bool
    {
        if ($e instanceof RestException && $e->getStatusCode() === 429) {
            return true;
        }

        if ($e->getCode() === 429) {
            return true;
        }

        if (method_exists($e, 'getResponse')) {
            $response = $e->getResponse();
            if (is_object($response) && method_exists($response, 'getStatusCode') && $response->getStatusCode() === 429) {
                return true;
            }
        }

        $previous = $e->getPrevious();
        if ($previous instanceof Throwable && self::isRateLimitException($previous)) {
            return true;
        }

        return self::isRateLimitMessage($e->getMessage());
    }

    /**
     * Determine if a message/error string indicates a rate limit issue.
     */
    public static function isRateLimitMessage(string $message): bool
    {
        return (bool) preg_match('/\b(rate[\s\-]*limit|too\s*many\s*(?:requests|calls|messages|actions|attempts)|429|over[\s\-_]*limit|retry[\s\-]*after)\b/i', $message);
    }
}
