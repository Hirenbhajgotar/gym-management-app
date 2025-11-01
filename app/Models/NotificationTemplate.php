<?php

namespace App\Models;

use App\Enums\NotificationTemplateTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'gym_owner_id',
        'type',
        'channel',
        'subject',
        'email_template',
        'whatsapp_template',
        'is_active',
    ];

    protected $casts = [
        'type' => NotificationTemplateTypeEnum::class,
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function gymOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gym_owner_id');
    }

    public function parseTemplate(array $variables): string
    {
        $template = $this->channel === 'email' ? $this->email_template : $this->whatsapp_template;

        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        return $template;
    }
}
