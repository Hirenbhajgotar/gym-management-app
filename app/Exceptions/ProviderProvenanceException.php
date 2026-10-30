<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A write tried to replace a persisted cloud provider credential without
 * re-submitting the server-derived `comm_key_source` provenance marker.
 *
 * `provider_config['comm_key']` is a provider credential when the persisted
 * config marks it as `cloud`. Dropping that marker would make the credential
 * look like an ADMS alias, so a later ADMS rotation or `--clean-plaintext`
 * backfill could delete it. An omitted marker on an unchanged value is
 * restored by the cast; a changed value must go through a genuine provider
 * transition (where the model derives provenance) instead.
 */
final class ProviderProvenanceException extends RuntimeException
{
    public static function whileReplacingCloudCredential(): self
    {
        return new self('Refusing to replace a cloud provider credential without explicit comm_key_source provenance.');
    }
}
