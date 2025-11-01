<?php

namespace App\Models;

use App\Enums\NotificationTemplateTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'user_id',
        'type',
        'channel',
        'status',
        'message',
        'response_data',
        'external_id',
        'sent_at',
    ];

    protected $casts = [
        'response_data' => 'array',
        'sent_at' => 'datetime',
        'type' => NotificationTemplateTypeEnum::class,
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
