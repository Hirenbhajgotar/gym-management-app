<?php

namespace App\Models;

use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Database\Factories\RequestContactFactory;
use App\Observers\RequestContactObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Propaganistas\LaravelPhone\Casts\RawPhoneNumberCast;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property PhoneNumber $phone
 * @property string|null $company_name
 * @property int|null $gym_size Number of members or size
 * @property string|null $message
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property bool $is_contacted
 * @property Carbon|null $contacted_at
 * @property string|null $admin_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|RequestContact contacted()
 * @method static RequestContactFactory factory($count = null, $state = [])
 * @method static Builder<static>|RequestContact newModelQuery()
 * @method static Builder<static>|RequestContact newQuery()
 * @method static Builder<static>|RequestContact query()
 * @method static Builder<static>|RequestContact recent()
 * @method static Builder<static>|RequestContact uncontacted()
 * @method static Builder<static>|RequestContact whereAdminNotes($value)
 * @method static Builder<static>|RequestContact whereCompanyName($value)
 * @method static Builder<static>|RequestContact whereContactedAt($value)
 * @method static Builder<static>|RequestContact whereCreatedAt($value)
 * @method static Builder<static>|RequestContact whereEmail($value)
 * @method static Builder<static>|RequestContact whereGymSize($value)
 * @method static Builder<static>|RequestContact whereId($value)
 * @method static Builder<static>|RequestContact whereIpAddress($value)
 * @method static Builder<static>|RequestContact whereIsContacted($value)
 * @method static Builder<static>|RequestContact whereMessage($value)
 * @method static Builder<static>|RequestContact whereName($value)
 * @method static Builder<static>|RequestContact wherePhone($value)
 * @method static Builder<static>|RequestContact whereUpdatedAt($value)
 * @method static Builder<static>|RequestContact whereUserAgent($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([RequestContactObserver::class])]
class RequestContact extends Model
{
    use HasFactory;

    public const NAVIGATION_BADGE_CACHE_KEY = 'request_contact_navigation_badge_count';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'gym_size',
        'message',
        'ip_address',
        'user_agent',
        'is_contacted',
        'contacted_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'gym_size' => 'integer',
            'is_contacted' => 'boolean',
            'contacted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'phone' => RawPhoneNumberCast::class,
        ];
    }

    /**
     * Scope for uncontacted requests
     */
    #[Scope]
    protected function uncontacted($query)
    {
        return $query->where('is_contacted', false);
    }

    /**
     * Scope for contacted requests
     */
    #[Scope]
    protected function contacted($query)
    {
        return $query->where('is_contacted', true);
    }

    /**
     * Scope for recent requests (last 30 days)
     */
    #[Scope]
    protected function recent($query)
    {
        return $query->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * Mark as contacted
     */
    public function markAsContacted(?string $notes = null): void
    {
        $this->update([
            'is_contacted' => true,
            'contacted_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);
    }
}
