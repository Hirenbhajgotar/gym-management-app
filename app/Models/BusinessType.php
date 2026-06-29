<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $slug
 * @property bool|null $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|BusinessType active()
 * @method static Builder<static>|BusinessType newModelQuery()
 * @method static Builder<static>|BusinessType newQuery()
 * @method static Builder<static>|BusinessType query()
 * @method static Builder<static>|BusinessType whereCreatedAt($value)
 * @method static Builder<static>|BusinessType whereId($value)
 * @method static Builder<static>|BusinessType whereIsActive($value)
 * @method static Builder<static>|BusinessType whereName($value)
 * @method static Builder<static>|BusinessType whereSlug($value)
 * @method static Builder<static>|BusinessType whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperBusinessType
 */
class BusinessType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
