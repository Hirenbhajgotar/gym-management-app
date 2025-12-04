<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Propaganistas\LaravelPhone\Casts\RawPhoneNumberCast;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $company_name
 * @property int|null $gym_size
 * @property string|null $message
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property bool $is_contacted
 * @property \Illuminate\Support\Carbon|null $contacted_at
 * @property string|null $admin_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestContact uncontacted()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestContact contacted()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestContact recent()
 */
class RequestContact extends Model
{
    use HasFactory;

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
    public function uncontacted($query)
    {
        return $query->where('is_contacted', false);
    }

    /**
     * Scope for contacted requests
     */
    #[Scope]
    public function contacted($query)
    {
        return $query->where('is_contacted', true);
    }

    /**
     * Scope for recent requests (last 30 days)
     */
    #[Scope]
    public function recent($query)
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
