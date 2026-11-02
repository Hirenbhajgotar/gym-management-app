<?php

declare(strict_types=1);

namespace App\Traits;

use RuntimeException;

/**
 * Refuse a compliance-critical write whose audit entry would not commit with it.
 *
 * The activity log is optional as a feature, but an erasure or a terminal deletion that
 * cannot be attested must not commit. `ACTIVITY_LOGGER_ENABLED=false` makes
 * `ActivityLogger::log()` return `null` without writing anything, and a separate
 * `activitylog.database_connection` commits the entry on another connection — in both
 * cases the write would commit unaudited if the caller only relied on an exception.
 *
 * Implemented once rather than copied into every model and Filament action, so the
 * delete, the discharge and the resend all enforce the same sink requirement.
 */
trait AssertsTransactionalAuditSink
{
    /**
     * @throws RuntimeException
     */
    protected function assertAuditSinkIsTransactional(): void
    {
        if (! config()->boolean('activitylog.enabled', true)) {
            throw new RuntimeException(
                'Cannot write this audit-critical record while the activity log is disabled: the change would commit without its audit entry.'
            );
        }

        $auditConnection = config('activitylog.database_connection');

        if (is_string($auditConnection) && $auditConnection !== '' && $auditConnection !== config('database.default')) {
            throw new RuntimeException(
                'Cannot write this audit-critical record while the activity log writes to a separate database connection: the audit entry would commit outside the transaction.'
            );
        }
    }
}
