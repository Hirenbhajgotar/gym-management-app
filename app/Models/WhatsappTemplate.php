<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    protected $fillable = [
        'gym_id',
        'template_name',
        'template_sid',
        'language',
        'variables',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Get the gym that owns the template
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    /**
     * Check if template is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
