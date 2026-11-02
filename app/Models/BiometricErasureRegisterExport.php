<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A durable copy of a tenant's outstanding erasure register, taken immediately before the
 * owner-purge command deletes the tenant.
 *
 * Deliberately not tenant-scoped and with no foreign key to `gyms`: the whole point is
 * that it survives the tenant cascade that destroys `biometric_templates`.
 *
 * @property int $id
 * @property int $gym_id
 * @property string|null $gym_name
 * @property int $obligation_count
 * @property array<int, array<string, mixed>> $obligations
 * @property Carbon $exported_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BiometricErasureRegisterExport extends Model
{
    protected $fillable = [
        'gym_id',
        'gym_name',
        'obligation_count',
        'obligations',
        'exported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'obligations' => 'array',
            'exported_at' => 'datetime',
            'obligation_count' => 'integer',
        ];
    }
}
