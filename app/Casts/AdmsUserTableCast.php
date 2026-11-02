<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\AdmsUserTableEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Tolerant cast for the snapshotted ADMS `user_table` column.
 *
 * The native enum cast throws a `ValueError` for any stored value outside the
 * two recognised schemas. That turned an out-of-band write — a restored dump,
 * a manual SQL fix, or a rollback across a schema change — into a 500 on the
 * device polling endpoint, so a single bad row could stop every terminal on the
 * fleet from receiving commands. An unrecognised value degrades to null here
 * and the serializer falls back to the configured default.
 *
 * Writes store the raw scalar; only the read path is lenient.
 *
 * @implements CastsAttributes<AdmsUserTableEnum|null, mixed>
 */
final class AdmsUserTableCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?AdmsUserTableEnum
    {
        return AdmsUserTableEnum::tryParse($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof AdmsUserTableEnum) {
            return $value->value;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
