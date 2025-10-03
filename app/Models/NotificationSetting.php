<?php

namespace App\Models;

use App\Enums\NotificationAccountStatusEnum;
use App\Enums\NotificationVerificationStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'gym_owner_id',
        'whatsapp_business_number',
        'whatsapp_api_key',
        'whatsapp_instance_id',
        'whatsapp_provider',
        'notification_types',
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_api_key',
        'twilio_api_secret',
        'account_status',
        'verification_status',
        'verified_at',
        'twilio_metadata',
    ];

    protected $casts = [
        'notification_types' => 'array',
        'twilio_metadata' => 'array',
        'verified_at' => 'datetime',
        'account_status' => NotificationAccountStatusEnum::class,
        'verification_status' => NotificationVerificationStatusEnum::class,
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function gymOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gym_owner_id');
    }

    public function isNotificationEnabled(string $type, string $channel = 'whatsapp'): bool
    {
        $types = $this->notification_types ?? [];
        return isset($types[$type]) && in_array($channel, $types[$type] ?? []);
    }
}
