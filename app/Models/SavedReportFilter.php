<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $user_id
 * @property string $name
 * @property string $report_type
 * @property array<array-key, mixed> $filters
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 * @property-read User $user
 *
 * @method static Builder<static>|SavedReportFilter forReport($reportType)
 * @method static Builder<static>|SavedReportFilter forUser($userId)
 * @method static Builder<static>|SavedReportFilter newModelQuery()
 * @method static Builder<static>|SavedReportFilter newQuery()
 * @method static Builder<static>|SavedReportFilter query()
 * @method static Builder<static>|SavedReportFilter whereCreatedAt($value)
 * @method static Builder<static>|SavedReportFilter whereFilters($value)
 * @method static Builder<static>|SavedReportFilter whereGymId($value)
 * @method static Builder<static>|SavedReportFilter whereId($value)
 * @method static Builder<static>|SavedReportFilter whereIsDefault($value)
 * @method static Builder<static>|SavedReportFilter whereName($value)
 * @method static Builder<static>|SavedReportFilter whereReportType($value)
 * @method static Builder<static>|SavedReportFilter whereUpdatedAt($value)
 * @method static Builder<static>|SavedReportFilter whereUserId($value)
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

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function forUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    #[Scope]
    protected function forReport($query, $reportType)
    {
        return $query->where('report_type', $reportType);
    }
}
