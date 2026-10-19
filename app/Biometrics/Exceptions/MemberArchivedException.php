<?php

declare(strict_types=1);

namespace App\Biometrics\Exceptions;

use RuntimeException;

class MemberArchivedException extends RuntimeException
{
    public function __construct(string $message = 'Member is archived. Enrollment is not permitted for archived members.')
    {
        parent::__construct($message);
    }
}
