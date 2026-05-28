<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $user_id
 * @property string $name
 * @property string $report_type
 * @property array<array-key, mixed> $filters
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter forReport($reportType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter forUser($userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter whereReportType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedReportFilter whereUserId($value)
 *
 * @mixin \Eloquent
 */
class SavedReportFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'user_id',
        'name',
        'report_type',
        'filters',
        'is_default',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_default' => 'boolean',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    public function forUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    #[Scope]
    public function forReport($query, $reportType)
    {
        return $query->where('report_type', $reportType);
    }
}
