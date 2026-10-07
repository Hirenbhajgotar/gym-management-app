<?php

declare(strict_types=1);

namespace App\Biometrics\Exceptions;

use RuntimeException;

class BiometricConsentRequiredException extends RuntimeException
{
    public function __construct(string $message = 'Member does not have active biometric consent on file.')
    {
        parent::__construct($message);
    }
}
