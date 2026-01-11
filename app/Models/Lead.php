<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Observers\LeadObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $membership_plan_id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property LeadSource $source
 * @property string|null $source_id
 * @property array<array-key, mixed>|null $raw_data
 * @property LeadStatus $status
 * @property int $order_column
 * @property array<array-key, mixed>|null $interest
 * @property array<array-key, mixed>|null $tags
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $last_follow_up_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LeadConversion|null $conversion
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FollowUp> $followUps
 * @property-read int|null $follow_ups_count
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\MembershipPlan|null $membershipPlan
 * @property-read \App\Models\User|null $updatedByUser
 *
 * @method static Builder<static>|Lead converted()
 * @method static \Database\Factories\LeadFactory factory($count = null, $state = [])
 * @method static Builder<static>|Lead lost()
 * @method static Builder<static>|Lead newModelQuery()
 * @method static Builder<static>|Lead newQuery()
 * @method static Builder<static>|Lead ordered(string $direction = 'asc')
 * @method static Builder<static>|Lead query()
 * @method static Builder<static>|Lead whereCreatedAt($value)
 * @method static Builder<static>|Lead whereCreatedBy($value)
 * @method static Builder<static>|Lead whereEmail($value)
 * @method static Builder<static>|Lead whereGymId($value)
 * @method static Builder<static>|Lead whereId($value)
 * @method static Builder<static>|Lead whereInterest($value)
 * @method static Builder<static>|Lead whereLastFollowUpDate($value)
 * @method static Builder<static>|Lead whereMembershipPlanId($value)
 * @method static Builder<static>|Lead whereName($value)
 * @method static Builder<static>|Lead whereNotes($value)
 * @method static Builder<static>|Lead whereOrderColumn($value)
 * @method static Builder<static>|Lead wherePhone($value)
 * @method static Builder<static>|Lead whereRawData($value)
 * @method static Builder<static>|Lead whereSource($value)
 * @method static Builder<static>|Lead whereSourceId($value)
 * @method static Builder<static>|Lead whereStatus($value)
 * @method static Builder<static>|Lead whereTags($value)
 * @method static Builder<static>|Lead whereUpdatedAt($value)
 * @method static Builder<static>|Lead whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperLead
 */
#[ObservedBy([LeadObserver::class])]
class Lead extends Model implements Sortable
{
    use HasFactory, SortableTrait;

    public const NAVIGATION_BADGE_CACHE_KEY = 'lead_navigation_badge_count_';

    protected $fillable = [
        'gym_id',
        'membership_plan_id',
        'name',
        'email',
        'phone',
        'source',
        'source_id',
        'raw_data',
        'status',
        'interest',
        'tags',
        'last_follow_up_date',
        'order_column',
        'notes',
        'created_by',
        'updated_by',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'raw_data' => 'array',
            'interest' => 'array',
            'tags' => 'array',
            'last_follow_up_date' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function conversion(): HasOne
    {
        return $this->hasOne(LeadConversion::class);
    }

    #[Scope]
    protected function converted(Builder $query): void
    {
        $query->where('status', LeadStatus::CONVERTED);
    }

    #[Scope]
    protected function lost(Builder $query): void
    {
        $query->where('status', LeadStatus::LOST);
    }
}
