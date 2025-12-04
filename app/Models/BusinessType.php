<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $slug
 * @property bool|null $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessType whereUpdatedAt($value)
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

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
