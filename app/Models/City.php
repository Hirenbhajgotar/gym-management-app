<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'state_id',
        'name',
        'slug',
        'status',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Scope a query to only include active countries.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to only include inactive countries.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 0);
    }
}
