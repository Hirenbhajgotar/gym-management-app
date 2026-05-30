<?php

namespace App\Jobs;

use Exception;

class RateLimitExceededException extends Exception
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        protected int $retryAfter = 5,
        int $code = 429,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
