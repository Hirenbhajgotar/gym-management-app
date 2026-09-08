<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\RateLimitExceededException as BaseException;

/**
 * Backward compatibility shim for RateLimitExceededException.
 *
 * @deprecated Moved to App\Exceptions\RateLimitExceededException in feature/staff-panel.
 *             Retained for backward compatibility with existing queued jobs and legacy callers.
 *             Will be removed in a future release.
 */
class RateLimitExceededException extends BaseException {}
