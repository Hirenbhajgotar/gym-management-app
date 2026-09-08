<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Helpers\WhatsAppSanitizer;
use Exception;
use Throwable;

class RateLimitExceededException extends Exception
{
    public const HTTP_TOO_MANY_REQUESTS = 429;

    public const DEFAULT_RETRY_AFTER = 5;

    public function __construct(
        string $message = 'Rate limit exceeded',
        protected int $retryAfter = self::DEFAULT_RETRY_AFTER,
        ?Throwable $previous = null,
    ) {
        $sanitizedMessage = WhatsAppSanitizer::sanitizeError($message);
        parent::__construct($sanitizedMessage, self::HTTP_TOO_MANY_REQUESTS, $previous);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
