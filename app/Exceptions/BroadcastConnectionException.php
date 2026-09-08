<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Helpers\WhatsAppSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class BroadcastConnectionException extends ConnectionException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message = 'Broadcast connection error',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $sanitizedMessage = WhatsAppSanitizer::sanitizeError($message);
        parent::__construct($sanitizedMessage, $code, $previous);
    }
}
