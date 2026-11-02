<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * The durable intent to release a tenant's Twilio resources after the tenant row is gone.
 *
 * Captured inside the `deleting` listener (so it rolls back with a refused delete) and
 * processed by {@see \App\Jobs\ReleaseTenantTwilioResources} after commit. The subaccount
 * token is encrypted at rest by the same accessor pattern as `WhatsAppAccount`; the job
 * needs it to build a subaccount client because the original row has been cascade-deleted.
 *
 * @property int $id
 * @property int $gym_id
 * @property string|null $twilio_account_sid
 * @property string|null $twilio_auth_token
 * @property string|null $messaging_service_sid
 * @property string|null $phone_number
 * @property string|null $channel_sender_sid
 * @property string|null $incoming_phone_sid
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WhatsAppAccountRelease extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RELEASED = 'released';

    public const STATUS_FAILED = 'failed';

    protected $table = 'whatsapp_account_releases';

    protected $fillable = [
        'gym_id',
        'twilio_account_sid',
        'twilio_auth_token',
        'messaging_service_sid',
        'phone_number',
        'channel_sender_sid',
        'incoming_phone_sid',
        'status',
        'attempts',
        'last_error',
        'released_at',
    ];

    protected $hidden = [
        'twilio_auth_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'released_at' => 'datetime',
        ];
    }

    protected function twilioAuthToken(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => (isset($attributes['twilio_auth_token']) && $attributes['twilio_auth_token'])
                ? Crypt::decrypt($attributes['twilio_auth_token'])
                : null,
            set: fn ($value) => $value ? Crypt::encrypt($value) : null,
        );
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }
}
